<?php

declare(strict_types=1);

namespace chrisjenkinson\DynamoDbSnapshottingRepository\Tests;

use Broadway\EventSourcing\EventSourcedAggregateRoot;
use Broadway\Serializer\Serializable;

final class TestAggregateRoot extends EventSourcedAggregateRoot implements Serializable
{
    private TestAggregateId $id;

    private int $total;

    public function getAggregateRootId(): string
    {
        return (string) $this->id;
    }

    public static function instantiateForReconstitution(): self
    {
        return new self();
    }

    public static function start(TestAggregateId $id): self
    {
        $aggregate = new self();
        $aggregate->apply(new TestAggregateStarted($id));

        return $aggregate;
    }

    public function add(): void
    {
        $this->apply(new TestAggregateAdded());
    }

    /**
     * @return array{id: string, total: int}
     */
    public function serialize(): array
    {
        return [
            'id'    => (string) $this->id,
            'total' => $this->total,
        ];
    }

    /**
     * @param array{id: string, total: int} $data
     */
    public static function deserialize(array $data): self
    {
        $aggregate        = new self();
        $aggregate->id    = new TestAggregateId($data['id']);
        $aggregate->total = $data['total'];

        return $aggregate;
    }

    protected function applyTestAggregateStarted(TestAggregateStarted $event): void
    {
        $this->id    = $event->id;
        $this->total = 0;
    }

    protected function applyTestAggregateAdded(TestAggregateAdded $event): void
    {
        $this->total++;
    }
}
