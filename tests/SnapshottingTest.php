<?php

declare(strict_types=1);

namespace chrisjenkinson\DynamoDbSnapshottingRepository\Tests;

use AsyncAws\Core\Configuration;
use AsyncAws\DynamoDb\DynamoDbClient;
use Broadway\Domain\DomainEventStream;
use Broadway\EventHandling\SimpleEventBus;
use Broadway\EventSourcing\AggregateFactory\NamedConstructorAggregateFactory;
use Broadway\EventSourcing\EventSourcingRepository;
use Broadway\EventStore\EventStore;
use Broadway\EventStore\InMemoryEventStore;
use Broadway\Repository\Repository;
use Broadway\Serializer\SimpleInterfaceSerializer;
use Broadway\Snapshotting\EventSourcing\SnapshottingEventSourcingRepository;
use Broadway\Snapshotting\Snapshot\Snapshot;
use Broadway\Snapshotting\Snapshot\SnapshotRepository;
use Broadway\Snapshotting\Snapshot\Snapshotter\SynchronousSnapshotter;
use chrisjenkinson\DynamoDbSnapshottingRepository\DynamoDbSnapshotRepository;
use chrisjenkinson\DynamoDbSnapshottingRepository\InputBuilder;
use chrisjenkinson\DynamoDbSnapshottingRepository\JsonDecoder;
use chrisjenkinson\DynamoDbSnapshottingRepository\JsonEncoder;
use PHPUnit\Framework\TestCase;

final class SnapshottingTest extends TestCase
{
    private EventStore $eventStore;

    private Repository $repository;

    private DynamoDbClient $client;

    private SnapshotRepository $snapshotRepository;

    private Repository $snapshottingRepository;

    private ConfigurableTrigger $trigger;

    private InputBuilder $inputBuilder;

    public function setUp(): void
    {
        $tableName = 'snapshots';

        $inMemoryEventStore = new InMemoryEventStore();

        $this->inputBuilder = new InputBuilder();

        $this->eventStore = new class($inMemoryEventStore) implements EventStore {
            public function __construct(
                private readonly EventStore $eventStore,
            ) {
            }

            public function load($id): DomainEventStream
            {
                return $this->eventStore->load($id);
            }

            public function loadFromPlayhead($id, int $playhead): DomainEventStream
            {
                return $this->eventStore->loadFromPlayhead($id, $playhead);
            }

            public function append($id, DomainEventStream $eventStream): void
            {
                $this->eventStore->append($id, $eventStream);
            }
        };

        $this->repository = new EventSourcingRepository(
            $this->eventStore,
            new SimpleEventBus(),
            TestAggregateRoot::class,
            new NamedConstructorAggregateFactory(),
            []
        );

        $this->client = new DynamoDbClient(
            Configuration::create([
                'endpoint'        => 'http://dynamodb-local:8000',
                'accessKeyId'     => 'none',
                'accessKeySecret' => 'none',
            ]),
        );

        $result = $this->client->tableNotExists($this->inputBuilder->buildDescribeTableInput($tableName));
        $result->resolve();

        if (!$result->isSuccess()) {
            $this->client->deleteTable($this->inputBuilder->buildDeleteTableInput($tableName));
        }

        $this->client->createTable($this->inputBuilder->buildCreateTableInput($tableName));

        $this->client->tableExists($this->inputBuilder->buildDescribeTableInput($tableName))->wait();

        $this->snapshotRepository = new DynamoDbSnapshotRepository(
            $this->client,
            $this->inputBuilder,
            $tableName,
            new SimpleInterfaceSerializer(),
            new JsonEncoder(),
            new JsonDecoder(),
        );

        $this->trigger = new ConfigurableTrigger();

        $this->snapshottingRepository = new SnapshottingEventSourcingRepository(
            $this->repository,
            $this->eventStore,
            $this->snapshotRepository,
            $this->trigger,
            new SynchronousSnapshotter($this->snapshotRepository),
        );
    }

    /**
     * @test
     */
    public function it_reconstitutes_an_aggregate_when_no_snapshot_found(): void
    {
        $this->trigger->shouldSnapshot = false;

        $id = new TestAggregateId('42');

        $aggregate = TestAggregateRoot::start($id);

        $aggregate->add();

        $this->snapshottingRepository->save($aggregate);

        $loadedAggregate = $this->snapshottingRepository->load($id);

        $this->assertEquals($aggregate, $loadedAggregate);
    }

    /**
     * @test
     */
    public function it_queries_the_event_store_for_events_recorded_after_playhead_of_snapshot(): void
    {
        $this->trigger->shouldSnapshot = true;

        $id = new TestAggregateId('42');

        $aggregate = TestAggregateRoot::start($id);

        $this->snapshottingRepository->save($aggregate);

        $this->trigger->shouldSnapshot = false;

        $aggregate->add();

        $this->snapshottingRepository->save($aggregate);

        $snapshot        = $this->snapshotRepository->load($id);
        $loadedAggregate = $this->snapshottingRepository->load($id);

        if (!$snapshot instanceof Snapshot) {
            $this->fail('Expected to load a Snapshot');
        }

        $this->assertEquals($aggregate, $loadedAggregate);
        $this->assertNotEquals($snapshot->getAggregateRoot(), $loadedAggregate);
    }
}
