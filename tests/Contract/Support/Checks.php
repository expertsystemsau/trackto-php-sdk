<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Tests\Contract\Support;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use ExpertSystems\TrackTo\Data\AnalyticsCountData;
use ExpertSystems\TrackTo\Data\AnalyticsDayData;
use ExpertSystems\TrackTo\Data\BulkResultData;
use ExpertSystems\TrackTo\Data\LinkAnalyticsData;
use ExpertSystems\TrackTo\Data\TagData;
use ExpertSystems\TrackTo\Exceptions\TrackToException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

/**
 * Pure drift checks between the vendored OpenAPI spec and the SDK. Each returns a list of problems,
 * so a negative proof passes a mutated copy instead of editing a file.
 */
final class Checks
{
    private const array HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    /** Schema names that do not follow the `XResource` to `XData` convention (D-11). */
    private const array DTO_EXCEPTIONS = ['LinkTagResource' => TagData::class];

    /**
     * Inline response schemas, which have no `XResource` name: operation => [response code, [property path => DTO]].
     * An empty path is the schema root; a path steps into `items` when its node is an array.
     *
     * @var array<string, array{string, array<string, class-string>}>
     */
    private const array INLINE_RESPONSES = [
        'GET /links/{}/analytics' => ['200', [
            '' => LinkAnalyticsData::class,
            'overTime' => AnalyticsDayData::class,
            'topReferrers' => AnalyticsCountData::class,
            'topCountries' => AnalyticsCountData::class,
        ]],
        'POST /links/bulk' => ['200', ['data' => BulkResultData::class]],
    ];

    private const string REQUESTS_NAMESPACE = 'ExpertSystems\\TrackTo\\Requests\\';

    private const string DATA_NAMESPACE = 'ExpertSystems\\TrackTo\\Data\\';

    /**
     * @return array<string, mixed>
     */
    public static function spec(): array
    {
        $spec = json_decode((string) file_get_contents(self::root().'/spec/openapi.json'), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($spec)) {
            throw new RuntimeException('spec/openapi.json is not a JSON object.');
        }

        return $spec;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return list<string>
     */
    public static function specOperations(array $spec): array
    {
        $operations = [];

        foreach ($spec['paths'] ?? [] as $path => $item) {
            foreach (self::HTTP_METHODS as $method) {
                if (isset($item[$method])) {
                    $operations[] = self::operation($method, (string) $path);
                }
            }
        }

        return $operations;
    }

    /**
     * Every concrete request class as `METHOD /path/{}`, matched by method and path template only (D-13).
     *
     * @return list<string>
     */
    public static function requestOperations(): array
    {
        $operations = [];

        foreach (self::classesIn('/packages/trackto-client/src/Requests', self::REQUESTS_NAMESPACE) as $class) {
            if ($class->isAbstract()) {
                continue;
            }

            $constructor = $class->getConstructor();
            $args = array_map(self::synthesise(...), $constructor?->getParameters() ?? []);
            $request = $class->newInstanceArgs($args);

            // An int id request synthesises /folders/1; a numeric segment stands for the spec's {param}.
            $path = (string) preg_replace('#/\d+(?=/|$)#', '/{}', rawurldecode($request->resolveEndpoint()));
            $operations[] = self::operation($request->getMethod()->value, $path);
        }

        return $operations;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  list<string>  $requestOps
     * @param  list<string>  $excluded
     * @return list<string>
     */
    public static function operations(array $spec, array $requestOps, array $excluded): array
    {
        $specOps = self::specOperations($spec);
        $excluded = array_map(self::normaliseOperation(...), $excluded);
        $problems = [];

        foreach (array_diff($specOps, $requestOps, $excluded) as $operation) {
            $problems[] = "Spec operation {$operation} has no request class and is not excluded.";
        }

        foreach (array_intersect($excluded, $requestOps) as $operation) {
            $problems[] = "Stale exclusion: {$operation} is excluded but a request class exists.";
        }

        foreach (array_diff($excluded, $specOps) as $operation) {
            $problems[] = "Excluded operation {$operation} is not in the spec.";
        }

        foreach (array_diff($requestOps, $specOps) as $operation) {
            $problems[] = "Request class operation {$operation} is not in the spec.";
        }

        return $problems;
    }

    /**
     * Constructor parameter names by schema name.
     *
     * @return array<string, list<string>>
     */
    public static function dtoParameters(): array
    {
        $parameters = [];

        foreach (self::classesIn('/packages/trackto-client/src/Data', self::DATA_NAMESPACE) as $class) {
            if (str_ends_with($class->getShortName(), 'Data') && ! in_array($class->getName(), self::inlineClasses(), true)) {
                $schema = substr($class->getShortName(), 0, -4).'Resource';
                $parameters[$schema] = self::parameterNames($class);
            }
        }

        foreach (self::DTO_EXCEPTIONS as $schema => $dto) {
            $parameters[$schema] = self::parameterNames(new ReflectionClass($dto));
        }

        return $parameters;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, list<string>>  $paramsBySchema
     * @return list<string>
     */
    public static function dtoFields(array $spec, array $paramsBySchema): array
    {
        $problems = [];

        foreach ($spec['components']['schemas'] ?? [] as $name => $schema) {
            if (! str_ends_with((string) $name, 'Resource')) {
                continue;
            }

            if (! isset($paramsBySchema[$name])) {
                $problems[] = "Schema {$name} has no DTO.";

                continue;
            }

            foreach (array_keys($schema['properties'] ?? []) as $property) {
                if (! in_array(lcfirst(str_replace('_', '', ucwords((string) $property, '_'))), $paramsBySchema[$name], true)) {
                    $problems[] = "{$name}.{$property} has no DTO parameter.";
                }
            }
        }

        return $problems;
    }

    /**
     * Property-for-parameter check of the inline response schemas, by operation (they have no schema name).
     *
     * @param  array<string, mixed>  $spec
     * @param  array<string, array{string, array<string, class-string>}>  $table
     * @return list<string>
     */
    public static function inlineFields(array $spec, array $table = self::INLINE_RESPONSES): array
    {
        $problems = [];

        foreach ($table as $operation => [$code, $nodes]) {
            $schema = self::inlineSchema($spec, $operation, $code);

            if ($schema === null) {
                $problems[] = "Inline response {$operation} {$code} is not in the spec.";

                continue;
            }

            foreach ($nodes as $path => $class) {
                $node = self::descend($schema, (string) $path);

                if ($node === null) {
                    $problems[] = "{$operation} {$path} is not in the spec schema.";

                    continue;
                }

                $reflection = new ReflectionClass($class);
                $parameters = self::parameterNames($reflection);

                foreach ($node['properties'] ?? [] as $property => $child) {
                    $label = $path === '' ? (string) $property : $path.'.'.$property;

                    if (! in_array(lcfirst(str_replace('_', '', ucwords((string) $property, '_'))), $parameters, true)) {
                        $problems[] = "{$operation} {$label} has no {$reflection->getShortName()} parameter.";
                    }

                    if (! isset($nodes[$label]) && self::isInline($child)) {
                        $problems[] = "{$operation} {$label} is an inline object with no INLINE_RESPONSES entry.";
                    }
                }
            }
        }

        return $problems;
    }

    /**
     * The spec has no enum for error names, only the prose list in the `name` description.
     *
     * @param  array<string, mixed>  $spec
     * @return list<string>
     */
    public static function specErrorCodes(array $spec): array
    {
        $description = $spec['components']['schemas']['ApiError']['properties']['name']['description'] ?? '';

        if (! is_string($description) || preg_match('/One of: ([a-z_, ]+)\./', $description, $match) !== 1) {
            return [];
        }

        return array_map(trim(...), explode(',', $match[1]));
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, class-string>  $byName
     * @return list<string>
     */
    public static function errorCodes(array $spec, array $byName): array
    {
        $codes = self::specErrorCodes($spec);

        if ($codes === []) {
            return ['The ApiError.name description has no parseable "One of: ..." code list.'];
        }

        $problems = [];

        foreach (array_diff($codes, array_keys($byName)) as $code) {
            $problems[] = "Spec error code {$code} has no mapped exception.";
        }

        foreach (array_diff(array_keys($byName), $codes) as $code) {
            $problems[] = "Mapped error code {$code} is not in the spec.";
        }

        foreach ($byName as $code => $class) {
            if (! is_subclass_of($class, TrackToException::class)) {
                $problems[] = "{$code} maps to {$class}, which is not a TrackToException.";

                continue;
            }

            $expected = str_replace('_', '', ucwords((string) preg_replace('/_error$/', '', $code), '_')).'Exception';

            if ((new ReflectionClass($class))->getShortName() !== $expected) {
                $problems[] = "{$code} maps to {$class}, expected a class named {$expected}.";
            }
        }

        return $problems;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return list<string>
     */
    public static function versionPin(array $spec, string $default): array
    {
        $version = $spec['info']['version'] ?? null;

        return $version === $default
            ? []
            : ['Default Track-Version '.$default.' differs from the spec info.version '.(is_string($version) ? $version : 'null').'.'];
    }

    public static function normaliseOperation(string $operation): string
    {
        [$method, $path] = explode(' ', $operation, 2);

        return self::operation($method, $path);
    }

    private static function operation(string $method, string $path): string
    {
        return strtoupper($method).' '.preg_replace('/\{[^}]*\}/', '{}', $path);
    }

    /**
     * @return list<class-string>
     */
    private static function inlineClasses(): array
    {
        return array_values(array_unique(array_merge(...array_map(
            static fn (array $entry): array => array_values($entry[1]),
            array_values(self::INLINE_RESPONSES),
        ))));
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>|null
     */
    private static function inlineSchema(array $spec, string $operation, string $code): ?array
    {
        foreach ($spec['paths'] ?? [] as $path => $item) {
            foreach (self::HTTP_METHODS as $method) {
                if (isset($item[$method]) && self::operation($method, (string) $path) === $operation) {
                    $schema = $item[$method]['responses'][$code]['content']['application/json']['schema'] ?? null;

                    return is_array($schema) ? $schema : null;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>|null
     */
    private static function descend(array $schema, string $path): ?array
    {
        $node = $schema;

        foreach ($path === '' ? [] : explode('.', $path) as $segment) {
            $node = $node['properties'][$segment] ?? null;

            if (! is_array($node)) {
                return null;
            }

            if (($node['type'] ?? null) === 'array') {
                $node = $node['items'] ?? null;

                if (! is_array($node)) {
                    return null;
                }
            }
        }

        return $node;
    }

    private static function isInline(mixed $property): bool
    {
        if (! is_array($property)) {
            return false;
        }

        return isset($property['properties']) || (($property['type'] ?? null) === 'array' && isset($property['items']['properties']));
    }

    private static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @return list<ReflectionClass<object>>
     */
    private static function classesIn(string $directory, string $namespace): array
    {
        $classes = [];

        foreach (glob(self::root().$directory.'/*.php') ?: [] as $file) {
            $classes[] = new ReflectionClass($namespace.basename($file, '.php'));
        }

        return $classes;
    }

    /**
     * @param  ReflectionClass<object>  $class
     * @return list<string>
     */
    private static function parameterNames(ReflectionClass $class): array
    {
        return array_map(
            static fn (ReflectionParameter $parameter): string => $parameter->getName(),
            $class->getConstructor()?->getParameters() ?? [],
        );
    }

    private static function synthesise(ReflectionParameter $parameter): mixed
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        $type = $parameter->getType();
        $name = $type instanceof ReflectionNamedType ? $type->getName() : 'string';

        return match (true) {
            $name === 'int' => 1,
            // A required array holds one item so a request that checks its count can be built.
            $name === 'array' => ['{p}'],
            is_subclass_of($name, BackedEnum::class) => $name::cases()[0],
            is_a($name, DateTimeInterface::class, true) => new DateTimeImmutable,
            default => '{p}',
        };
    }
}
