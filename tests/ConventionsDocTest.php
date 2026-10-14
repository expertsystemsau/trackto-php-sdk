<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Exceptions\ConfigurationException;
use ExpertSystems\TrackTo\Exceptions\ConnectionException;
use ExpertSystems\TrackTo\Exceptions\ErrorMapper;
use ExpertSystems\TrackTo\Exceptions\TrackToException;
use ExpertSystems\TrackTo\Exceptions\UnexpectedResponseException;
use ExpertSystems\TrackTo\Laravel\Facades\TrackTo;
use ExpertSystems\TrackTo\Pagination\LinksPaginator;
use ExpertSystems\TrackTo\Resources\FoldersResource;
use ExpertSystems\TrackTo\Resources\LinksResource;
use ExpertSystems\TrackTo\Resources\TagsResource;
use ExpertSystems\TrackTo\Support\RateLimitInfo;
use ExpertSystems\TrackTo\TrackToClient;
use Symfony\Component\Yaml\Yaml;

function conventionsDoc(): string
{
    return (string) file_get_contents(dirname(__DIR__).'/docs/conventions.md');
}

/**
 * @param  list<string>  $except
 * @return list<string>
 */
function conventionsParameters(ReflectionMethod $method, array $except = []): array
{
    return array_values(array_diff(array_map(static fn (ReflectionParameter $p): string => $p->getName(), $method->getParameters()), $except));
}

/**
 * @return array<string, string> Body of each `## ` section, keyed by heading.
 */
function conventionsSections(?string $doc = null): array
{
    $parts = preg_split('/^## (.+)$/m', $doc ?? conventionsDoc(), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $sections = [];

    for ($i = 1; $i < count($parts); $i += 2) {
        $sections[$parts[$i]] = $parts[$i + 1];
    }

    return $sections;
}

/**
 * @return list<string> The body of every fenced php block.
 */
function conventionsPhpBlocks(string $markdown): array
{
    preg_match_all('/^```php\n(.*?)^```/ms', $markdown, $matches);

    return $matches[1];
}

/**
 * Methods the doc shows as `public function` blocks, and the class each one is checked against.
 *
 * @var array<string, class-string>
 */
const CONVENTIONS_METHOD_CLASSES = [
    'update' => LinksResource::class,
    'bulkMove' => LinksResource::class,
    'bulkTag' => LinksResource::class,
    'bulkDelete' => LinksResource::class,
    'fake' => TrackTo::class,
];

function conventionsShortName(string $type): string
{
    return ltrim((string) strrchr('\\'.$type, '\\'), '\\');
}

/**
 * @return list<string> Sorted type members; `?T` is `{T, null}`.
 */
function conventionsTypeSet(string $type): array
{
    $members = array_map(conventionsShortName(...), explode('|', ltrim($type, '?')));

    if (str_starts_with($type, '?')) {
        $members[] = 'null';
    }

    sort($members);

    return array_values(array_unique($members));
}

/**
 * @return list<string> Sorted type members of a reflected parameter.
 */
function conventionsReflectedTypeSet(ReflectionParameter $parameter): array
{
    $type = $parameter->getType();
    $members = match (true) {
        $type instanceof ReflectionUnionType => array_map(static fn (ReflectionNamedType $t): string => $t->getName(), array_values(array_filter($type->getTypes(), static fn ($t): bool => $t instanceof ReflectionNamedType))),
        $type instanceof ReflectionNamedType => [$type->getName(), ...($type->allowsNull() && $type->getName() !== 'mixed' && $type->getName() !== 'null' ? ['null'] : [])],
        default => [],
    };
    $members = array_map(conventionsShortName(...), $members);
    sort($members);

    return array_values(array_unique($members));
}

function conventionsReflectedDefault(ReflectionParameter $parameter): ?string
{
    if (! $parameter->isDefaultValueAvailable()) {
        return null;
    }

    if ($parameter->isDefaultValueConstant()) {
        return conventionsShortName((string) $parameter->getDefaultValueConstantName());
    }

    $value = $parameter->getDefaultValue();

    return strtolower($value === [] ? '[]' : var_export($value, true));
}

/**
 * Compares every `public function` block in $doc with reflection: parameter names, order, types and defaults.
 * shortcut: a comma or a `)` inside a quoted default would split wrongly, and parentheses nest one level only (`new Foo()` works);
 * no documented default has either, tokenize with PhpToken if one appears.
 *
 * @return list<string> One line per mismatch; empty when the doc matches the code.
 */
function conventionsSignatureMismatches(string $doc): array
{
    preg_match_all('/public (?:static )?function (\w+)\(((?:[^()]|\([^()]*\))*)\)(?::\s*[\w\\\\|?]+)?;/s', $doc, $blocks, PREG_SET_ORDER);

    $problems = $blocks === [] ? ['no public function block found'] : [];

    foreach ($blocks as [, $method, $list]) {
        $class = CONVENTIONS_METHOD_CLASSES[$method] ?? null;

        if ($class === null) {
            $problems[] = "{$method}(): no class mapped for this documented method";

            continue;
        }

        $actual = (new ReflectionMethod($class, $method))->getParameters();
        $documented = array_values(array_filter(array_map('trim', explode(',', (string) preg_replace('/#\[[^\]]*\]/', '', $list)))));
        $names = [];

        foreach ($documented as $position => $parameter) {
            preg_match('/^(?:(?<type>[^$]+?)\s+)?\$(?<name>\w+)(?:\s*=\s*(?<default>.+))?$/s', $parameter, $parts);
            $name = $parts['name'] ?? $parameter;
            $names[] = $name;
            $reflected = $actual[$position] ?? null;

            if ($reflected === null || $reflected->getName() !== $name) {
                continue;
            }

            if (conventionsTypeSet($parts['type'] ?? '') !== conventionsReflectedTypeSet($reflected)) {
                $problems[] = "{$method}(\${$name}): documented type differs from the code";
            }

            $default = isset($parts['default']) ? trim($parts['default']) : null;
            $expected = conventionsReflectedDefault($reflected);

            if (($default === null ? null : (str_contains($default, '::') ? $default : strtolower($default))) !== $expected) {
                $problems[] = "{$method}(\${$name}): documented default differs from the code";
            }
        }

        if ($names !== array_map(static fn (ReflectionParameter $p): string => $p->getName(), $actual)) {
            $problems[] = "{$method}(): documented parameter names or order differ from the code";
        }
    }

    return $problems;
}

/**
 * Blanks string literals (underscores) and comments (spaces), keeping every offset, so bracket and comma scanning cannot be fooled by them.
 */
function conventionsMaskLiterals(string $code): string
{
    $out = '';
    $length = strlen($code);

    for ($i = 0; $i < $length; $i++) {
        $char = $code[$i];
        $two = substr($code, $i, 2);

        if ($char === '\'' || $char === '"') {
            $end = $i + 1;

            while ($end < $length && $code[$end] !== $char) {
                $end += $code[$end] === '\\' ? 2 : 1;
            }

            $end = min($end, $length - 1);
            $out .= $char.str_repeat('_', max(0, $end - $i - 1)).$code[$end];
            $i = $end;
        } elseif ($two === '//' || $two === '/*') {
            $end = $two === '//' ? (strpos($code, "\n", $i) ?: $length) : (($close = strpos($code, '*/', $i + 2)) === false ? $length : $close + 2);
            $out .= str_repeat(' ', $end - $i);
            $i = $end - 1;
        } else {
            $out .= $char;
        }
    }

    return $out;
}

/**
 * Parameter names a call passes by name, or null for an argument that is positional.
 *
 * @return list<string|null>
 */
function conventionsCallArguments(string $code, string $call): array
{
    $code = conventionsMaskLiterals($code);
    $found = [];
    $offset = 0;

    while (($at = strpos($code, $call, $offset)) !== false) {
        $depth = 1;
        $arg = '';
        $args = [];

        for ($i = $at + strlen($call); $i < strlen($code) && $depth > 0; $i++) {
            $char = $code[$i];
            $depth += match ($char) {
                '(', '[' => 1,
                ')', ']' => -1,
                default => 0,
            };

            if ($depth === 1 && $char === ',' || $depth === 0) {
                $args[] = trim($arg);
                $arg = '';
            } else {
                $arg .= $char;
            }
        }

        foreach (array_filter($args, static fn (string $a): bool => $a !== '') as $argument) {
            $found[] = preg_match('/^(\w+):(?!:)/', $argument, $m) === 1 ? $m[1] : null;
        }

        $offset = $at + 1;
    }

    return $found;
}

function conventionsDefault(ReflectionMethod $method, string $parameter): mixed
{
    foreach ($method->getParameters() as $candidate) {
        if ($candidate->getName() === $parameter) {
            return $candidate->getDefaultValue();
        }
    }

    throw new LogicException("No parameter {$parameter} on {$method->getName()}().");
}

it('has exactly the sixteen sections, found by name', function (): void {
    preg_match_all('/^## (.+)$/m', conventionsDoc(), $matches);

    expect($matches[1])->toEqualCanonicalizing([
        'Client shape', 'Resources and methods', 'Data objects', 'Exceptions', 'Pagination', 'Updates', 'Bulk actions', 'Analytics',
        'Folders and tags', 'Retry', 'Timeouts and transport', 'Testing', 'Laravel', 'Versioning', 'Security notes', 'Contract harness',
    ]);
});

it('names every exception class', function (): void {
    $classes = [...array_values(ErrorMapper::BY_NAME), TrackToException::class, ConnectionException::class, UnexpectedResponseException::class, ConfigurationException::class];

    foreach ($classes as $class) {
        expect(conventionsDoc())->toContain((new ReflectionClass($class))->getShortName());
    }

    expect(conventionsDoc())->toContain('Exceptions\InvalidArgumentException');

    foreach (array_keys(ErrorMapper::BY_NAME) as $code) {
        expect(conventionsDoc())->toContain('`'.$code.'`');
    }
});

/**
 * The call each operation is documented under, and the class that defines its parameters.
 *
 * @return array<string, array{string, class-string, string, list<string>}>
 */
function conventionsCalls(): array
{
    return [
        'client constructor' => ['new TrackToClient(', TrackToClient::class, '__construct', ['sleeper']],
        'links list' => ['links()->list(', LinksResource::class, 'list', []],
        'links all' => ['links()->all(', LinksResource::class, 'all', []],
        'links create' => ['links()->create(', LinksResource::class, 'create', []],
        'links update' => ['links()->update(', LinksResource::class, 'update', []],
        'links delete' => ['links()->delete(', LinksResource::class, 'delete', []],
        'links analytics' => ['links()->analytics(', LinksResource::class, 'analytics', []],
        'links bulkMove' => ['links()->bulkMove(', LinksResource::class, 'bulkMove', []],
        'links bulkTag' => ['links()->bulkTag(', LinksResource::class, 'bulkTag', []],
        'links bulkDelete' => ['links()->bulkDelete(', LinksResource::class, 'bulkDelete', []],
        'folders create' => ['folders()->create(', FoldersResource::class, 'create', []],
        'folders delete' => ['folders()->delete(', FoldersResource::class, 'delete', []],
        'tags create' => ['tags()->create(', TagsResource::class, 'create', []],
        'tags delete' => ['tags()->delete(', TagsResource::class, 'delete', []],
    ];
}

it('shows every parameter as a named argument in the block that calls the method', function (string $call, string $class, string $method, array $except): void {
    $expected = conventionsParameters(new ReflectionMethod($class, $method), $except);
    $complete = array_filter(
        conventionsPhpBlocks(conventionsDoc()),
        static fn (string $block): bool => str_contains($block, $call) && array_diff($expected, array_filter(conventionsCallArguments($block, $call))) === [],
    );

    expect($complete)->not->toBeEmpty("No php block calls {$call} with every parameter by name.");
})->with(conventionsCalls());

it('pins the documented signatures to reflection: names, order, types and defaults', function (): void {
    expect(conventionsSignatureMismatches(conventionsDoc()))->toBe([]);
});

it('fails the signature pin when a documented type or default drifts', function (string $from, string $to, string $parameter): void {
    $doc = conventionsDoc();

    expect($doc)->toContain($from);

    $mismatches = conventionsSignatureMismatches(str_replace($from, $to, $doc));

    expect($mismatches)->not->toBeEmpty()
        ->and(implode("\n", $mismatches))->toContain($parameter);
})->with([
    'default' => ['array|Omitted $tags = Omitted::Value', 'array|Omitted $tags = null', 'update($tags)'],
    'type' => ['int|Omitted|null $folderId = Omitted::Value', 'string|Omitted|null $folderId = Omitted::Value', 'update($folderId)'],
    'order' => ['array $slugs, array $tags', 'array $tags, array $slugs', 'bulkTag()'],
    'unmapped method' => ['public function bulkDelete(', 'public function bulkErase(', 'bulkErase()'],
]);

it('lists every with method the client declares', function (): void {
    $declared = array_filter(
        (new ReflectionClass(TrackToClient::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        static fn (ReflectionMethod $m): bool => str_starts_with($m->getName(), 'with') && $m->getDeclaringClass()->getName() === TrackToClient::class,
    );

    expect($declared)->not->toBeEmpty();

    foreach ($declared as $method) {
        expect(conventionsDoc())->toContain('`'.$method->getName().'()`');
    }
});

it('states the timeout and retry defaults the code ships', function (): void {
    $constructor = new ReflectionMethod(TrackToClient::class, '__construct');
    $connect = (int) conventionsDefault($constructor, 'connectTimeout');
    $timeout = (int) conventionsDefault($constructor, 'timeout');
    $maxWait = (int) conventionsDefault(new ReflectionMethod(TrackToClient::class, 'withRetry'), 'maxWait');
    $tries = (new TrackToClient(apiKey: 'conventions-key'))->tries;

    expect(conventionsDoc())
        ->toContain("connect timeout of {$connect} seconds")
        ->toContain("request timeout of {$timeout} seconds")
        ->toContain("{$maxWait}-second cap")
        ->toContain("{$tries} attempts")
        ->toContain('https://'.parse_url(TrackToClient::DEFAULT_BASE_URL, PHP_URL_HOST))
        ->toContain(TrackToClient::DEFAULT_VERSION);
});

it('records the update semantics', function (): void {
    expect(conventionsDoc())
        ->toContain('Omitted')
        ->toContain('removePassword: true')
        ->toContain('`null` means clear')
        ->toContain('unchanged')
        ->toContain('`tags: []`')
        ->not->toContain('provisional')
        ->not->toContain('Phase 12');
});

it('records the bulk promises', function (): void {
    expect(conventionsDoc())
        ->toContain('between 1 and 100 slugs')
        ->toContain('never chunked')
        ->toContain('never retried')
        ->toContain('changes nothing')
        ->toContain('partial results')
        ->toContain('re-list');
});

it('records the remaining operations', function (): void {
    expect(conventionsDoc())
        ->toContain('analytics(')
        ->toContain('folders()')
        ->toContain('tags()')
        ->toContain('unpaginated')
        ->toContain('10,000 pages')
        ->toContain('`headers()` and `config()`')
        ->toContain('no-op')
        ->toContain('inspect `response()` before retrying')
        ->toContain('`folder()`')
        ->toContain('`tag()`')
        ->toContain('`analytics()`')
        ->toContain('`bulk()`');
});

it('records the accepted exception dump residual', function (): void {
    expect(conventionsDoc())
        ->toContain('var_export')
        ->toContain('dd(')
        ->toContain('Authorization')
        ->toContain('log `getMessage()` and `status()`');
});

it('states that a 429 reset timing is honoured only when the RateLimit header shows no budget left', function (): void {
    expect(conventionsDoc())->toContain('`RateLimit` header showing no budget left')
        ->and(RateLimitInfo::fromHeaders(['RateLimit' => '"default";r=3;t=1'])->retryAfter)->toBeNull()
        ->and(RateLimitInfo::fromHeaders(['RateLimit' => '"default";r=0;t=1'])->retryAfter)->toBe(1);
});

it('pins the Laravel fake() signature the facade ships', function (): void {
    $fake = new ReflectionMethod(TrackTo::class, 'fake');
    $parameter = $fake->getParameters()[0];
    $return = $fake->getReturnType();

    expect($return)->toBeInstanceOf(ReflectionNamedType::class)
        ->and(conventionsDoc())->toContain(sprintf(
            'public static function fake(%s $%s = %s): %s',
            $parameter->getType(),
            $parameter->getName(),
            $parameter->getDefaultValue() === [] ? '[]' : var_export($parameter->getDefaultValue(), true),
            (new ReflectionClass($return instanceof ReflectionNamedType ? $return->getName() : ''))->getShortName(),
        ));
});

it('names every Laravel config key', function (): void {
    /** @var array<string, mixed> $config */
    $config = require dirname(__DIR__).'/packages/trackto-laravel/config/trackto.php';

    expect($config)->not->toBeEmpty();

    foreach (array_keys($config) as $key) {
        expect(conventionsDoc())->toContain('`'.$key.'`');
    }
});

it('keeps the Laravel README promises', function (): void {
    $readme = (string) file_get_contents(dirname(__DIR__).'/packages/trackto-laravel/README.md');

    foreach ([
        'vendor:publish --tag=trackto-config',
        'TRACKTO_API_KEY', 'TRACKTO_BASE_URL', 'TRACKTO_API_VERSION', 'TRACKTO_CONNECT_TIMEOUT', 'TRACKTO_TIMEOUT', 'TRACKTO_USER_AGENT_SUFFIX',
        'withApiKey(', 'TrackTo::fake(', 'assertSent(', 'trackto:ping', 'trackto:shorten', 'octane.warm', 'authenticate()', 'saloonphp/laravel-plugin',
    ] as $promise) {
        expect($readme)->toContain($promise);
    }
});

it('records the live per_page probe and that the server clamps', function (): void {
    $pagination = conventionsSections()['Pagination'] ?? '';
    $max = (new ReflectionProperty(LinksPaginator::class, 'maxPages'))->getDefaultValue();

    preg_match_all('/^\| `?(-?\d+)`? \| (\d+) \| (\d+) \|$/m', $pagination, $rows, PREG_SET_ORDER);

    expect(array_column($rows, null, 1))->toHaveCount(5)
        ->and(array_map(static fn (array $row): array => [$row[2], $row[3]], array_column($rows, null, 1)))->toBe([
            '0' => ['200', '25'], '1' => ['200', '1'], '100' => ['200', '100'], '101' => ['200', '100'], '-1' => ['200', '1'],
        ])
        ->and($pagination)->toContain('clamps')
        ->toContain('no client-side guard')
        ->toContain(number_format((int) $max).' pages');
});

it('puts each promise in the section it belongs to', function (string $section, array $phrases): void {
    $body = conventionsSections()[$section] ?? '';

    expect($body)->not->toBe('');

    foreach ($phrases as $phrase) {
        expect($body)->toContain($phrase);
    }
})->with([
    'versioning' => ['Versioning', ['lockstep', '0.x', 'may break', TrackToClient::DEFAULT_VERSION, '1.0.0']],
    'testing' => ['Testing', ['Request classes are public', 'assertSent()', 'unsupported', 'sendAndRetry()', 'single-attempt guarantee']],
    'security' => ['Security notes', ['serialize()', 'plain `\Exception`']],
    'exceptions' => ['Exceptions', ['403 `forbidden`', '401 `unauthorized`', '`Authorization` header is absent']],
    'harness' => ['Contract harness', ['`required`', 'out of scope']],
]);

it('keeps undecided wording out of the conventions', function (): void {
    expect(preg_match('/\b(TBD|TODO|to be decided|open question|undecided)\b/i', conventionsDoc()))->toBe(0);
});

it('calls the client only with named arguments that exist, in the client README', function (): void {
    $readme = (string) file_get_contents(dirname(__DIR__).'/packages/trackto-client/README.md');
    $blocks = conventionsPhpBlocks($readme);
    $checked = [];

    foreach (conventionsCalls() as $label => [$call, $class, $method]) {
        $valid = conventionsParameters(new ReflectionMethod($class, $method));

        foreach ($blocks as $block) {
            foreach (conventionsCallArguments($block, $call) as $name) {
                $checked[$label] = ($checked[$label] ?? 0) + 1;
                expect($name)->toBeIn($valid, "{$call} in the README passes an argument that is positional or not a parameter of {$method}().");
            }
        }
    }

    // Pinned per call: a README edit that adds or drops a call must update this on purpose.
    expect($checked)->toBe(['client constructor' => 1, 'links list' => 2, 'links create' => 2, 'folders create' => 1, 'tags create' => 1]);
});

it('scans arguments without being fooled by brackets, commas or slashes inside strings', function (): void {
    $code = "\$c->links()->create(url: 'https://cdn.example/a(b,c]', slug: \"x,y\", // note (\n tags: ['a, b']);";

    expect(conventionsCallArguments($code, 'links()->create('))->toBe(['url', 'slug', 'tags'])
        ->and(conventionsCallArguments("\$c->links()->create('a', 'b');", 'links()->create('))->toBe([null, null]);
});

function conventionsReadme(string $package): string
{
    return (string) file_get_contents(dirname(__DIR__)."/packages/{$package}/README.md");
}

/**
 * Cells of the first data row under `## Compatibility`.
 *
 * @return list<string>
 */
function conventionsCompatibilityRow(string $readme): array
{
    $section = conventionsSections($readme)['Compatibility'] ?? '';
    $rows = array_values(array_filter(explode("\n", $section), static fn (string $line): bool => str_starts_with($line, '|')));

    return array_map('trim', explode('|', trim($rows[2] ?? '', '|')));
}

it('states in each README the versions the manifests, the CI matrix and the pinned API version support', function (string $package, string $laravel): void {
    $matrix = Yaml::parseFile(dirname(__DIR__).'/.github/workflows/tests.yml')['jobs']['tests']['strategy']['matrix'];
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__)."/packages/{$package}/composer.json"), true, flags: JSON_THROW_ON_ERROR);
    $php = array_map(strval(...), $matrix['php']);

    expect(ltrim($manifest['require']['php'], '^'))->toBe($php[0]);

    if ($laravel === '') {
        $majors = array_map(static fn (string $range): string => explode('.', ltrim($range, '^'))[0], explode('||', $manifest['require']['illuminate/support']));

        expect($majors)->toBe(array_map(static fn (string $lane): string => explode('.', $lane)[0], $matrix['laravel']));
        $laravel = implode(', ', $majors);
    }

    $readme = conventionsReadme($package);

    expect(substr_count($readme, "\n## Compatibility\n"))->toBe(1)
        ->and(strpos($readme, '## Compatibility'))->toBeLessThan(strpos($readme, '## License'))
        ->and(substr_count($readme, '| 0.1.x |'))->toBe(1)
        ->and(array_slice(conventionsCompatibilityRow($readme), 1))->toBe([implode(', ', $php), $laravel, TrackToClient::DEFAULT_VERSION])
        ->and(conventionsCompatibilityRow($readme)[0])->toMatch('/^\d+\.\d+\.x$/');
})->with([
    'client' => ['trackto-client', 'any (framework-agnostic)'],
    'laravel' => ['trackto-laravel', ''],
]);

it('keeps release-pending wording out of the READMEs and shows the install commands', function (): void {
    foreach (['trackto-client', 'trackto-laravel'] as $package) {
        expect(preg_match('/Not yet released|Available from the first tagged release/', conventionsReadme($package)))->toBe(0);
    }

    expect(conventionsReadme('trackto-client'))->toContain('composer require expertsystemsau/trackto-php-client')
        ->toContain('https://github.com/expertsystemsau/trackto-php-sdk/blob/main/docs/conventions.md')
        ->and(conventionsReadme('trackto-laravel'))->toContain('composer require expertsystemsau/trackto-laravel-client');
});

it('shows the client README quick start, errors and testing sections', function (): void {
    $readme = conventionsReadme('trackto-client');

    foreach (['## Install', '## Quick start', '## Errors', '## Testing', 'links()->create(', 'links()->list(', 'links()->all(', 'folders()->', 'tags()->', 'TrackToException', 'RateLimitedException', 'rateLimit()', 'withMockClient(', 'Fixtures'] as $needle) {
        expect($readme)->toContain($needle);
    }
});
