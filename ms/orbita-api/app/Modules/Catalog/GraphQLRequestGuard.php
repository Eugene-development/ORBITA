<?php
namespace App\Modules\Catalog;

use Closure;
use GraphQL\Language\Parser;
use GraphQL\Utils\AST;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Enforces GraphQL-over-HTTP method safety independently of Lighthouse execution. */
final class GraphQLRequestGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->isMethod('GET') ? $request->query() : $request->json()->all();
        if (array_is_list($input) || isset($input['operations']) || $request->files->count() > 0) {
            return $this->reject('GraphQL batch and multipart requests are disabled.', 400);
        }
        $source = $input['query'] ?? null;
        if (! is_string($source) || strlen($source) > 65536) { return $this->reject('A GraphQL query up to 64 KiB is required.', 400); }
        if ($request->isMethod('GET')) {
            try {
                $name = $input['operationName'] ?? null;
                if ($name !== null && ! is_string($name)) { return $this->reject('Invalid operation name.', 400); }
                $operation = AST::getOperationAST(Parser::parse($source), $name);
                if (! $operation) { return $this->reject('Select one valid GraphQL operation.', 400); }
                if ($operation->operation !== 'query') { return $this->reject('GraphQL mutations require POST and CSRF protection.', 405); }
            } catch (\Throwable) { return $this->reject('Invalid GraphQL document.', 400); }
        }
        return $next($request);
    }
    private function reject(string $message, int $status): Response
    {
        return response()->json(['errors' => [['message' => $message]]], $status);
    }
}
