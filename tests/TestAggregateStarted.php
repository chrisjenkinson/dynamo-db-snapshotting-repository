<?php

declare(strict_types=1);

namespace chrisjenkinson\DynamoDbSnapshottingRepository\Tests;

final class TestAggregateStarted
{
    public function __construct(
        public readonly TestAggregateId $id,
    ) {
    }
}
