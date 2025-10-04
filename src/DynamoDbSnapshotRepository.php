<?php

declare(strict_types=1);

namespace chrisjenkinson\DynamoDbSnapshottingRepository;

use AsyncAws\DynamoDb\DynamoDbClient;
use Broadway\EventSourcing\EventSourcedAggregateRoot;
use Broadway\Serializer\Serializable;
use Broadway\Serializer\Serializer;
use Broadway\Snapshotting\Snapshot\Snapshot;
use Broadway\Snapshotting\Snapshot\SnapshotRepository;
use ReflectionClass;
use ReflectionObject;
use UnexpectedValueException;

final class DynamoDbSnapshotRepository implements SnapshotRepository
{
    public function __construct(
        private readonly DynamoDbClient $client,
        private readonly InputBuilder $inputBuilder,
        private readonly string $table,
        private readonly Serializer $serializer,
        private readonly JsonEncoder $jsonEncoder,
        private readonly JsonDecoder $jsonDecoder,
    ) {
    }

    public function load($id): ?Snapshot
    {
        $id = (string) $id;

        $input = $this->inputBuilder->buildGetItemInput($this->table, $id);

        $result = $this->client->getItem($input);

        if ([] === $item = $result->getItem()) {
            return null;
        }

        $payload = $item['Payload']->getS();

        if (!is_string($payload)) {
            throw new UnexpectedValueException('Snapshot payload is not a string');
        }

        $aggregateRoot = $this->serializer->deserialize($this->jsonDecoder->decode($payload));

        $reflection = new ReflectionObject($aggregateRoot);
        while ($reflection->getParentClass() instanceof ReflectionClass && EventSourcedAggregateRoot::class !== $reflection->getName()) {
            $reflection = $reflection->getParentClass();
        }
        $property = $reflection->getProperty('playhead');
        $property->setValue($aggregateRoot, (int) $item['Playhead']->getN());

        return new Snapshot($aggregateRoot);
    }

    public function save(Snapshot $snapshot): void
    {
        $aggregateRoot = $snapshot->getAggregateRoot();
        $playhead      = $snapshot->getPlayhead();

        if (!$aggregateRoot instanceof Serializable) {
            return;
        }

        $input = $this->inputBuilder->buildPutItemInput(
            $this->table,
            $aggregateRoot->getAggregateRootId(),
            $playhead,
            $this->jsonEncoder->encode($this->serializer->serialize($aggregateRoot)),
        );

        $this->client->putItem($input);
    }
}
