<?php

declare(strict_types=1);

namespace chrisjenkinson\DynamoDbSnapshottingRepository\Tests;

use Broadway\EventSourcing\EventSourcedAggregateRoot;
use Broadway\Snapshotting\Snapshot\Trigger;

final class ConfigurableTrigger implements Trigger
{
    public bool $shouldSnapshot = false;

    public function shouldSnapshot(EventSourcedAggregateRoot $aggregate): bool
    {
        return $this->shouldSnapshot;
    }
}
