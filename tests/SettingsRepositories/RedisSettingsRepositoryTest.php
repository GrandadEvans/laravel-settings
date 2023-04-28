<?php

namespace Spatie\LaravelSettings\Tests\SettingsRepositories;

use Illuminate\Redis\RedisManager;
use function PHPUnit\Framework\assertEqualsCanonicalizing;

use Spatie\LaravelSettings\SettingsRepositories\RedisSettingsRepository;

beforeEach(function () {
    $this->client = resolve(RedisManager::class)->client();

    $this->client->flushAll();

    $this->repository = resolve(RedisSettingsRepository::class, [
        'config' => [],
    ]);
});

it('can get the properties in a group', function () {
    $this->repository->createProperty('test', 'a', 'Alpha');
    $this->repository->createProperty('test', 'b', true);
    $this->repository->createProperty('test', 'c', ['night', 'day']);
    $this->repository->createProperty('test', 'd', null);
    $this->repository->createProperty('test', 'e', 42);

    $this->repository->createProperty('not-test', 'a', 'Alpha');

    $properties = $this->repository->getPropertiesInGroup('test');

    expect($properties)
        ->toHaveCount(5)
        ->toEqual([
            'a' => 'Alpha',
            'b' => true,
            'c' => ['night', 'day'],
            'd' => null,
            'e' => 42,
        ]);
});

it('can check if a property exists', function () {
    $this->repository->createProperty('test', 'a', 'a');

    expect($this->repository->checkIfPropertyExists('test', 'a'))->toBeTrue();
    expect($this->repository->checkIfPropertyExists('test', 'b'))->toBeFalse();
});

it('can get the property payload', function () {
    $this->client->hMSet('test', [
        'a' => json_encode('Alpha'),
        'b' => json_encode(true),
        'c' => json_encode(['night', 'day']),
        'd' => json_encode(null),
        'e' => json_encode(42),
    ]);

    expect($this->repository->getPropertyPayload('test', 'a'))->toEqual('Alpha');
    expect($this->repository->getPropertyPayload('test', 'b'))->toBeTrue();
    expect($this->repository->getPropertyPayload('test', 'c'))->toEqual(['night', 'day']);
    expect($this->repository->getPropertyPayload('test', 'd'))->toBeNull();
    expect($this->repository->getPropertyPayload('test', 'e'))->toEqual(42);
});

it('can create a property', function () {
    $this->repository->createProperty('test', 'a', 'Alpha');
    $this->repository->createProperty('test', 'b', true);
    $this->repository->createProperty('test', 'c', ['night', 'day']);
    $this->repository->createProperty('test', 'd', null);
    $this->repository->createProperty('test', 'e', 42);

    expect($this->client->hLen('test'))->toEqual(5);

    $values = $this->client->hVals('test');
    $keys = $this->client->hKeys('test');

    expect($keys[0])->toEqual('a');
    expect(json_decode($values[0]))->toEqual('Alpha');

    expect($keys[1])->toEqual('b');
    expect(json_decode($values[1]))->toBeTrue();

    expect($keys[2])->toEqual('c');
    expect(json_decode($values[2], true))->toEqual(['night', 'day']);

    expect($keys[3])->toEqual('d');
    expect(json_decode($values[3], true))->toEqual(null);

    expect($keys[4])->toEqual('e');
    expect(json_decode($values[4], true))->toEqual(42);
});

it('can update a property payload', function () {
    $this->repository->createProperty('test', 'a', 'Alpha');
    $this->repository->createProperty('test', 'b', true);
    $this->repository->createProperty('test', 'c', ['night', 'day']);
    $this->repository->createProperty('test', 'd', null);
    $this->repository->createProperty('test', 'e', 42);

    $newValues = [
        'a' => null,
        'b' => false,
        'c' => ['light', 'dark'],
        'd' => 'Alpha',
        'e' => 69,
    ];

    $this->repository->updatePropertiesPayload('test', $newValues);

    expect($this->repository->getPropertyPayload('test', 'a'))->toBeNull();
    expect($this->repository->getPropertyPayload('test', 'b'))->toBeFalse();
    expect($this->repository->getPropertyPayload('test', 'c'))->toEqual(['light', 'dark']);
    expect($this->repository->getPropertyPayload('test', 'd'))->toEqual('Alpha');
    expect($this->repository->getPropertyPayload('test', 'e'))->toEqual(69);
});


it('can update a properties payload', function () {
    $this->repository->createProperty('test', 'a', 'Alpha');
    $this->repository->createProperty('test', 'b', true);
    $this->repository->createProperty('test', 'c', ['night', 'day']);
    $this->repository->createProperty('test', 'd', null);
    $this->repository->createProperty('test', 'e', 42);

    $properties = [
        'a' => null,
        'b' => false,
        'c' => ['light', 'dark'],
        'd' => 'Alpha',
        'e' => 69,
    ];

    $this->repository->updatePropertiesPayload('test', $properties);

    expect($this->repository->getPropertyPayload('test', 'a'))->toBeNull();
    expect($this->repository->getPropertyPayload('test', 'b'))->toBeFalse();
    expect($this->repository->getPropertyPayload('test', 'c'))->toEqual(['light', 'dark']);
    expect($this->repository->getPropertyPayload('test', 'd'))->toEqual('Alpha');
    expect($this->repository->getPropertyPayload('test', 'e'))->toEqual(69);
});

it('can delete a property', function () {
    $this->repository->createProperty('test', 'a', 'Alpha');

    $this->repository->deleteProperty('test', 'a');

    expect($this->client->hExists('test', 'a'))->toBeFalse();
});

it('can lock settings', function () {
    $this->repository->createProperty('test', 'a', 'Alpha');
    $this->repository->createProperty('test', 'b', 'Beta');
    $this->repository->createProperty('test', 'c', 'Gamma');

    $this->repository->lockProperties('test', ['a', 'c']);

    assertEqualsCanonicalizing(['a', 'c'], $this->client->sMembers('locks.test'));
});

it('can unlock settings', function () {
    $this->repository->createProperty('test', 'a', 'Alpha');
    $this->repository->createProperty('test', 'b', 'Beta');
    $this->repository->createProperty('test', 'c', 'Gamma');

    $this->repository->lockProperties('test', ['a', 'b', 'c']);

    $this->repository->unlockProperties('test', ['a', 'c']);

    assertEqualsCanonicalizing(['b'], $this->client->sMembers('locks.test'));
});

it('can get the locked properties', function () {
    $this->repository->createProperty('test', 'a', 'Alpha');
    $this->repository->createProperty('test', 'b', 'Beta');
    $this->repository->createProperty('test', 'c', 'Gamma');

    $this->repository->lockProperties('test', ['a', 'c']);

    $lockedProperties = $this->repository->getLockedProperties('test');

    expect($lockedProperties)
        ->toHaveCount(2)
        ->toContain('a')
        ->toContain('c');
});

it('can use a prefix', function () {
    $this->repository = resolve(RedisSettingsRepository::class, [
        'config' => ['prefix' => 'spatie'],
    ]);

    $this->repository->createProperty('test', 'a', 'Alpha');

    expect($this->client->hGetAll('spatie.test'))
        ->toEqual(['a' => json_encode('Alpha')]);

    expect($this->repository->getPropertiesInGroup('test'))
        ->toEqual(['a' => 'Alpha']);

    expect($this->repository->checkIfPropertyExists('test', 'a'))->toBeTrue();

    expect($this->repository->getPropertyPayload('test', 'a'))->toEqual('Alpha');

    $this->repository->updatePropertiesPayload('test', ['a' => 'Alpha Updated']);

    expect($this->client->hGet('spatie.test', 'a'))
        ->toEqual(json_encode('Alpha Updated'));

    $this->repository->lockProperties('test', ['a']);

    expect($this->repository->getLockedProperties('test'))->toEqual(['a']);

    expect($this->client->sMembers('spatie.locks.test'))->toEqual(['a']);

    $this->repository->unlockProperties('test', ['a']);

    expect($this->client->sMembers('spatie.locks.test'))->toBeEmpty();

    $this->repository->deleteProperty('test', 'a');

    expect($this->repository->getPropertiesInGroup('test'))->toBeEmpty();
    expect($this->client->hGetAll('spatie.test'))->toBeEmpty();
});
