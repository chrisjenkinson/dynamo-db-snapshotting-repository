<?php

declare(strict_types=1);

namespace chrisjenkinson\DynamoDbSnapshottingRepository\Tests;

final class TestAggregateId
{
    public function __construct(
        public readonly string $id
    ) {
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
