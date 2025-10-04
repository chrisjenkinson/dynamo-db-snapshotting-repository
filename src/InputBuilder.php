<?php

declare(strict_types=1);

namespace chrisjenkinson\DynamoDbSnapshottingRepository;

use AsyncAws\DynamoDb\Input\CreateTableInput;
use AsyncAws\DynamoDb\Input\DeleteTableInput;
use AsyncAws\DynamoDb\Input\DescribeTableInput;
use AsyncAws\DynamoDb\Input\GetItemInput;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\ValueObject\AttributeDefinition;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use AsyncAws\DynamoDb\ValueObject\KeySchemaElement;

final class InputBuilder
{
    public function buildGetItemInput(string $table, string $id): GetItemInput
    {
        return new GetItemInput([
            'TableName' => $table,
            'Key'       => [
                'Id' => new AttributeValue([
                    'S' => $id,
                ]),
            ],
        ]);
    }

    public function buildPutItemInput(string $table, string $id, int $playhead, string $payload): PutItemInput
    {
        return new PutItemInput([
            'TableName' => $table,
            'Item'      => [
                'Id' => new AttributeValue([
                    'S' => $id,
                ]),
                'Playhead' => new AttributeValue([
                    'N' => (string) $playhead,
                ]),
                'Payload' => new AttributeValue([
                    'S' => $payload,
                ]),
            ],
        ]);
    }

    public function buildDescribeTableInput(string $table): DescribeTableInput
    {
        return new DescribeTableInput([
            'TableName' => $table,
        ]);
    }

    public function buildDeleteTableInput(string $table): DeleteTableInput
    {
        return new DeleteTableInput([
            'TableName' => $table,
        ]);
    }

    public function buildCreateTableInput(string $table): CreateTableInput
    {
        return new CreateTableInput([
            'TableName'            => $table,
            'AttributeDefinitions' => [
                new AttributeDefinition([
                    'AttributeName' => 'Id',
                    'AttributeType' => 'S',
                ]),
            ],
            'BillingMode' => 'PAY_PER_REQUEST',
            'KeySchema'   => [
                new KeySchemaElement([
                    'AttributeName' => 'Id',
                    'KeyType'       => 'HASH',
                ]),
            ],
        ]);
    }
}
