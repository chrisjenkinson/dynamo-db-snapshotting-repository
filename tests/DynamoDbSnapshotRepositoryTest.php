<?php

declare(strict_types=1);

namespace chrisjenkinson\DynamoDbSnapshottingRepository\Tests;

use AsyncAws\Core\Configuration;
use AsyncAws\DynamoDb\DynamoDbClient;
use Broadway\Serializer\SimpleInterfaceSerializer;
use Broadway\Snapshotting\Snapshot\Snapshot;
use Broadway\Snapshotting\Snapshot\SnapshotRepository;
use chrisjenkinson\DynamoDbSnapshottingRepository\DynamoDbSnapshotRepository;
use chrisjenkinson\DynamoDbSnapshottingRepository\InputBuilder;
use chrisjenkinson\DynamoDbSnapshottingRepository\JsonDecoder;
use chrisjenkinson\DynamoDbSnapshottingRepository\JsonEncoder;
use PHPUnit\Framework\TestCase;

final class DynamoDbSnapshotRepositoryTest extends TestCase
{
    private DynamoDbClient $client;

    protected SnapshotRepository $repository;

    protected function createRepository(): SnapshotRepository
    {
        $this->client = new DynamoDbClient(
            Configuration::create([
                'endpoint'        => 'http://dynamodb-local:8000',
                'accessKeyId'     => 'none',
                'accessKeySecret' => 'none',
            ]),
        );

        $inputBuilder = new InputBuilder();
        $tableName    = 'snapshots';

        $result = $this->client->tableNotExists($inputBuilder->buildDescribeTableInput($tableName));
        $result->resolve();

        if (!$result->isSuccess()) {
            $this->client->deleteTable($inputBuilder->buildDeleteTableInput($tableName));
        }

        $this->client->createTable($inputBuilder->buildCreateTableInput($tableName));

        $this->client->tableExists($inputBuilder->buildDescribeTableInput($tableName))->wait();

        return new DynamoDbSnapshotRepository(
            $this->client,
            new InputBuilder(),
            'snapshots',
            new SimpleInterfaceSerializer(),
            new JsonEncoder(),
            new JsonDecoder(),
        );
    }

    /**
     * @test
     */
    public function it_implements__snapshot_repository(): void
    {
        $this->assertInstanceOf(SnapshotRepository::class, $this->repository);
    }

    /**
     * @test
     */
    public function it_returns_null_when_no_snapshot_available(): void
    {
        $this->assertNull($this->repository->load('no-snapshot'));
    }

    /**
     * @test
     */
    public function it_returns_snapshot_when_available(): void
    {
        $id        = new TestAggregateId('id');
        $aggregate = $this->createAggregateWithHistory(5);
        $this->repository->save(new Snapshot($aggregate));

        $actual = $this->repository->load($id);

        $this->assertEquals(
            new Snapshot($aggregate),
            $actual
        );
    }

    /**
     * @test
     */
    public function it_does_not_mutate_state_of__snapshot__aggregate_after_persisting(): void
    {
        $id = new TestAggregateId('id');

        $aggregate = $this->createAggregateWithHistory(5);
        $this->repository->save(new Snapshot($aggregate));

        // Applying another event to Snapshotted Aggregate should not affect Snapshot version
        $aggregate->apply(new TestAggregateAdded());
        $aggregate->getUncommittedEvents();

        $snapshot = $this->repository->load($id);
        $this->assertEquals(new Snapshot($this->createAggregateWithHistory(5)), $snapshot);
    }

    /**
     * @test
     */
    public function it_does_not_mutate_state_of__snapshot__aggregate_after_loading(): void
    {
        $id        = new TestAggregateId('id');
        $aggregate = $this->createAggregateWithHistory(5);
        $this->repository->save(new Snapshot($aggregate));

        $snapshot = $this->repository->load($id);

        if (!$snapshot instanceof Snapshot) {
            $this->fail('Expected to load a Snapshot');
        }

        $loadedAggregate = $snapshot->getAggregateRoot();
        $loadedAggregate->apply(new TestAggregateAdded());
        $loadedAggregate->getUncommittedEvents();

        $this->assertEquals(
            new Snapshot($this->createAggregateWithHistory(5)),
            $this->repository->load($id)
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createRepository();
    }

    private function createAggregateWithHistory(int $numberOfEvents): TestAggregateRoot
    {
        $id        = new TestAggregateId('id');
        $aggregate = TestAggregateRoot::start($id);
        for ($i = 0; $i < $numberOfEvents; ++$i) {
            $aggregate->apply(new TestAggregateAdded());
        }
        $aggregate->getUncommittedEvents(); // Flush events

        return $aggregate;
    }
}
