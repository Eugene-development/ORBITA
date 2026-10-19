<?php
namespace App\Modules\Catalog;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Error\Error;
final class DecimalScalar extends ScalarType {
    public function serialize(mixed $value): string { return Decimal::normalize((string) $value); }
    public function parseValue(mixed $value): string { return Decimal::normalize($value); }
    public function parseLiteral(Node $valueNode, ?array $variables = null): string {
        if (! $valueNode instanceof StringValueNode) { throw new Error('Decimal values must be quoted strings.', $valueNode); }
        return Decimal::normalize($valueNode->value);
    }
}
