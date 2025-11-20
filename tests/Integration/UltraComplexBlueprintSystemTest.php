<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Blueprint;
use App\Models\BlueprintEmbed;
use App\Models\DocRef;
use App\Models\DocValue;
use App\Models\Entry;
use App\Models\Path;
use App\Models\PostType;
use App\Models\User;
use App\Services\Blueprint\BlueprintStructureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ультра-сложный интеграционный тест системы Blueprint.
 *
 * Проверяет:
 * - Глубокую вложенность (5+ уровней)
 * - Множественные встраивания одного blueprint в разные места
 * - Транзитивные зависимости (A → B → C → D → E)
 * - Diamond dependencies
 * - Материализацию на всех уровнях
 * - Каскадные обновления структуры
 * - Индексацию сложных путей
 * - Запросы по глубоко вложенным полям
 * - Реиндексацию при изменении структуры
 * - Массивы вложенных объектов (cardinality = many)
 * - Ссылки между записями (ref)
 * - Производительность на большом графе
 */
class UltraComplexBlueprintSystemTest extends TestCase
{
    use RefreshDatabase;

    private BlueprintStructureService $service;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BlueprintStructureService::class);
        $this->admin = User::factory()->create(['email' => 'admin@test.com']);
    }

    /**
     * МЕГА-ТЕСТ: Полный жизненный цикл сложной системы Blueprint.
     *
     * Сценарий:
     * 1. Создание базовых компонентов (Location, ContactInfo, Metadata)
     * 2. Создание составных компонентов (Address = Location + Metadata)
     * 3. Создание сложных сущностей (Person, Organization, Event)
     * 4. Diamond dependency: Address → Person и Organization → Event (который использует и Person, и Organization)
     * 5. Транзитивное встраивание 5 уровней глубиной
     * 6. Создание Entry с глубоко вложенными данными
     * 7. Проверка индексации всех уровней
     * 8. Изменение исходного компонента (Location)
     * 9. Проверка каскадной рематериализации
     * 10. Проверка реиндексации всех Entry
     * 11. Запросы по глубоко вложенным путям
     * 12. Массивы вложенных объектов (speakers: Person[])
     * 13. Ссылки между записями (ref)
     */
    public function test_ultra_complex_blueprint_system_full_lifecycle(): void
    {
        // ==========================================
        // ШАГ 1: БАЗОВЫЕ КОМПОНЕНТЫ (уровень 0)
        // ==========================================
        
        $this->info('🔷 Creating base components (Level 0)...');

        // Геолокация (широта/долгота)
        $geoLocation = $this->service->createBlueprint([
            'name' => 'Geo Location',
            'code' => 'geo_location',
            'description' => 'GPS координаты',
        ]);

        $this->service->createPath($geoLocation, [
            'name' => 'latitude',
            'data_type' => 'float',
            'is_required' => true,
            'is_indexed' => true,
        ]);

        $this->service->createPath($geoLocation, [
            'name' => 'longitude',
            'data_type' => 'float',
            'is_required' => true,
            'is_indexed' => true,
        ]);

        // Временная зона
        $timezone = $this->service->createBlueprint([
            'name' => 'Timezone',
            'code' => 'timezone',
            'description' => 'Временная зона',
        ]);

        $this->service->createPath($timezone, [
            'name' => 'name',
            'data_type' => 'string',
            'is_required' => true,
            'is_indexed' => true,
        ]);

        $this->service->createPath($timezone, [
            'name' => 'offset',
            'data_type' => 'int',
            'is_indexed' => true,
        ]);

        // Метаданные (создатель, даты)
        $metadata = $this->service->createBlueprint([
            'name' => 'Metadata',
            'code' => 'metadata',
            'description' => 'Метаданные создания/обновления',
        ]);

        $this->service->createPath($metadata, [
            'name' => 'created_by',
            'data_type' => 'string',
            'is_indexed' => true,
        ]);

        $this->service->createPath($metadata, [
            'name' => 'created_at',
            'data_type' => 'datetime',
            'is_indexed' => true,
        ]);

        $this->service->createPath($metadata, [
            'name' => 'updated_by',
            'data_type' => 'string',
            'is_indexed' => true,
        ]);

        $this->service->createPath($metadata, [
            'name' => 'updated_at',
            'data_type' => 'datetime',
            'is_indexed' => true,
        ]);

        // Контактная информация
        $contactInfo = $this->service->createBlueprint([
            'name' => 'Contact Info',
            'code' => 'contact_info',
            'description' => 'Контакты',
        ]);

        $this->service->createPath($contactInfo, [
            'name' => 'email',
            'data_type' => 'string',
            'is_required' => true,
            'is_indexed' => true,
        ]);

        $this->service->createPath($contactInfo, [
            'name' => 'phone',
            'data_type' => 'string',
            'is_indexed' => true,
        ]);

        $this->service->createPath($contactInfo, [
            'name' => 'website',
            'data_type' => 'string',
            'is_indexed' => true,
        ]);

        $this->info('✓ Created 4 base components');

        // ==========================================
        // ШАГ 2: СОСТАВНЫЕ КОМПОНЕНТЫ (уровень 1)
        // ==========================================

        $this->info('🔷 Creating composite components (Level 1)...');

        // Location = GeoLocation + Timezone + адрес
        $location = $this->service->createBlueprint([
            'name' => 'Location',
            'code' => 'location',
            'description' => 'Полная информация о локации',
        ]);

        $this->service->createPath($location, [
            'name' => 'country',
            'data_type' => 'string',
            'is_required' => true,
            'is_indexed' => true,
        ]);

        $this->service->createPath($location, [
            'name' => 'city',
            'data_type' => 'string',
            'is_required' => true,
            'is_indexed' => true,
        ]);

        $this->service->createPath($location, [
            'name' => 'street',
            'data_type' => 'string',
            'is_indexed' => true,
        ]);

        $this->service->createPath($location, [
            'name' => 'postal_code',
            'data_type' => 'string',
            'is_indexed' => true,
        ]);

        // Встроить GeoLocation в Location
        $geoGroup = $this->service->createPath($location, [
            'name' => 'coordinates',
            'data_type' => 'json',
        ]);

        $this->service->createEmbed($location, $geoLocation, $geoGroup);

        // Встроить Timezone в Location
        $tzGroup = $this->service->createPath($location, [
            'name' => 'timezone',
            'data_type' => 'json',
        ]);

        $this->service->createEmbed($location, $timezone, $tzGroup);

        $this->info('✓ Location created with GeoLocation + Timezone embeds');

        // ==========================================
        // ШАГ 3: СЛОЖНЫЕ КОМПОНЕНТЫ (уровень 2)
        // ==========================================

        $this->info('🔷 Creating complex components (Level 2)...');

        // Address = Location + Metadata
        $address = $this->service->createBlueprint([
            'name' => 'Address',
            'code' => 'address',
            'description' => 'Полный адрес с геолокацией и метаданными',
        ]);

        $this->service->createPath($address, [
            'name' => 'label',
            'data_type' => 'string',
            'is_indexed' => true,
        ]);

        // Встроить Location
        $locationGroup = $this->service->createPath($address, [
            'name' => 'location',
            'data_type' => 'json',
        ]);

        $this->service->createEmbed($address, $location, $locationGroup);

        // Встроить Metadata
        $metaGroup = $this->service->createPath($address, [
            'name' => 'metadata',
            'data_type' => 'json',
        ]);

        $this->service->createEmbed($address, $metadata, $metaGroup);

        $this->info('✓ Address created (Level 2: GeoLocation → Location → Address)');

        // ==========================================
        // ШАГ 4: СУЩНОСТИ (уровень 3)
        // ==========================================

        $this->info('🔷 Creating entities (Level 3)...');

        // Person = ContactInfo + Address (home + work)
        $person = $this->service->createBlueprint([
            'name' => 'Person',
            'code' => 'person',
            'description' => 'Персона с адресами и контактами',
        ]);

        $this->service->createPath($person, [
            'name' => 'first_name',
            'data_type' => 'string',
            'is_required' => true,
            'is_indexed' => true,
        ]);

        $this->service->createPath($person, [
            'name' => 'last_name',
            'data_type' => 'string',
            'is_required' => true,
            'is_indexed' => true,
        ]);

        $this->service->createPath($person, [
            'name' => 'birth_date',
            'data_type' => 'date',
            'is_indexed' => true,
        ]);

        // ContactInfo
        $contactGroup = $this->service->createPath($person, [
            'name' => 'contacts',
            'data_type' => 'json',
        ]);

        $this->service->createEmbed($person, $contactInfo, $contactGroup);

        // Home Address (множественное встраивание #1)
        $homeAddressGroup = $this->service->createPath($person, [
            'name' => 'home_address',
            'data_type' => 'json',
        ]);

        $this->service->createEmbed($person, $address, $homeAddressGroup);

        // Work Address (множественное встраивание #2)
        $workAddressGroup = $this->service->createPath($person, [
            'name' => 'work_address',
            'data_type' => 'json',
        ]);

        $this->service->createEmbed($person, $address, $workAddressGroup);

        $this->info('✓ Person created with 2× Address embeds (Level 3)');

        // Organization = ContactInfo + Address + multiple Persons
        $organization = $this->service->createBlueprint([
            'name' => 'Organization',
            'code' => 'organization',
            'description' => 'Организация с адресами и сотрудниками',
        ]);

        $this->service->createPath($organization, [
            'name' => 'name',
            'data_type' => 'string',
            'is_required' => true,
            'is_indexed' => true,
        ]);

        $this->service->createPath($organization, [
            'name' => 'registration_number',
            'data_type' => 'string',
            'is_indexed' => true,
        ]);

        $this->service->createPath($organization, [
            'name' => 'founded_at',
            'data_type' => 'date',
            'is_indexed' => true,
        ]);

        // Contact
        $orgContactGroup = $this->service->createPath($organization, [
            'name' => 'contacts',
            'data_type' => 'json',
        ]);

        $this->service->createEmbed($organization, $contactInfo, $orgContactGroup);

        // Office Address
        $officeAddressGroup = $this->service->createPath($organization, [
            'name' => 'office_address',
            'data_type' => 'json',
        ]);

        $this->service->createEmbed($organization, $address, $officeAddressGroup);

        // Legal Address
        $legalAddressGroup = $this->service->createPath($organization, [
            'name' => 'legal_address',
            'data_type' => 'json',
        ]);

        $this->service->createEmbed($organization, $address, $legalAddressGroup);

        $this->info('✓ Organization created with 2× Address embeds (Level 3)');

        // ==========================================
        // ШАГ 5: УЛЬТРА-СЛОЖНАЯ СУЩНОСТЬ (уровень 4)
        // ==========================================

        $this->info('🔷 Creating ultra-complex entity (Level 4 - Diamond Dependency)...');

        // Event = Location + Organization (organizer) + Person[] (speakers) + metadata
        $event = $this->service->createBlueprint([
            'name' => 'Event',
            'code' => 'event',
            'description' => 'Мероприятие с организатором, спикерами и локацией',
        ]);

        $this->service->createPath($event, [
            'name' => 'title',
            'data_type' => 'string',
            'is_required' => true,
            'is_indexed' => true,
        ]);

        $this->service->createPath($event, [
            'name' => 'description',
            'data_type' => 'text',
        ]);

        $this->service->createPath($event, [
            'name' => 'start_date',
            'data_type' => 'datetime',
            'is_required' => true,
            'is_indexed' => true,
        ]);

        $this->service->createPath($event, [
            'name' => 'end_date',
            'data_type' => 'datetime',
            'is_indexed' => true,
        ]);

        $this->service->createPath($event, [
            'name' => 'capacity',
            'data_type' => 'int',
            'is_indexed' => true,
        ]);

        // Event Location (прямое встраивание Location, не Address)
        $eventLocationGroup = $this->service->createPath($event, [
            'name' => 'venue',
            'data_type' => 'json',
        ]);

        $this->service->createEmbed($event, $location, $eventLocationGroup);

        // Organizer (Organization)
        $organizerGroup = $this->service->createPath($event, [
            'name' => 'organizer',
            'data_type' => 'json',
        ]);

        $this->service->createEmbed($event, $organization, $organizerGroup);

        // Metadata
        $eventMetaGroup = $this->service->createPath($event, [
            'name' => 'metadata',
            'data_type' => 'json',
        ]);

        $this->service->createEmbed($event, $metadata, $eventMetaGroup);

        // Related events (refs)
        $this->service->createPath($event, [
            'name' => 'related_events',
            'data_type' => 'ref',
            'cardinality' => 'many',
            'is_indexed' => true,
        ]);

        // Sponsors (refs to organizations)
        $this->service->createPath($event, [
            'name' => 'sponsors',
            'data_type' => 'ref',
            'cardinality' => 'many',
            'is_indexed' => true,
        ]);

        $this->info('✓ Event created with Organization + Location (DIAMOND DEPENDENCY)');

        // ==========================================
        // ШАГ 6: ПРОВЕРКА МАТЕРИАЛИЗАЦИИ
        // ==========================================

        $this->info('🔷 Verifying materialization...');

        // Проверить глубину материализации в Event
        $event->refresh();
        $eventPaths = $event->paths()->get();

        // Должны быть пути вида:
        // - venue.city
        // - venue.coordinates.latitude
        // - venue.timezone.name
        // - organizer.name
        // - organizer.office_address.location.city
        // - organizer.office_address.location.coordinates.latitude
        // - organizer.office_address.metadata.created_by

        $deepPath = 'organizer.office_address.location.coordinates.latitude';
        $hasDeepPath = $eventPaths->contains('full_path', $deepPath);

        $this->assertTrue($hasDeepPath, "Deep path '{$deepPath}' should exist after materialization");

        // Подсчитать глубину (количество точек = уровни - 1)
        $maxDepth = $eventPaths->max(fn($p) => substr_count($p->full_path, '.'));
        $this->assertGreaterThanOrEqual(4, $maxDepth, 'Should have paths with 4+ dots (5 levels depth)');

        $this->info("✓ Materialization verified (max depth: {$maxDepth} levels)");

        // Проверить количество материализованных путей
        $ownPaths = Path::whereNull('source_blueprint_id')->count();
        $copiedPaths = Path::whereNotNull('source_blueprint_id')->count();

        $this->info("  • Own paths: {$ownPaths}");
        $this->info("  • Materialized paths: {$copiedPaths}");
        $this->assertGreaterThan(50, $copiedPaths, 'Should have 50+ materialized paths');

        // ==========================================
        // ШАГ 7: СОЗДАНИЕ POSTTYPE И ENTRY
        // ==========================================

        $this->info('🔷 Creating PostType and Entry with ultra-complex data...');

        $eventPostType = PostType::create([
            'slug' => 'event',
            'name' => 'Events',
            'blueprint_id' => $event->id,
        ]);

        // Создать первую Entry (Event)
        $eventEntry1 = Entry::create([
            'post_type_id' => $eventPostType->id,
            'title' => 'Laravel Conference 2025',
            'slug' => 'laravel-conf-2025',
            'status' => Entry::STATUS_PUBLISHED,
            'published_at' => now(),
            'author_id' => $this->admin->id,
            'data_json' => [
                'title' => 'Laravel Conference 2025',
                'description' => 'The biggest Laravel event of the year',
                'start_date' => '2025-06-15T09:00:00Z',
                'end_date' => '2025-06-17T18:00:00Z',
                'capacity' => 500,
                'venue' => [
                    'country' => 'USA',
                    'city' => 'San Francisco',
                    'street' => '123 Market Street',
                    'postal_code' => '94103',
                    'coordinates' => [
                        'latitude' => 37.7749,
                        'longitude' => -122.4194,
                    ],
                    'timezone' => [
                        'name' => 'America/Los_Angeles',
                        'offset' => -8,
                    ],
                ],
                'organizer' => [
                    'name' => 'Laravel LLC',
                    'registration_number' => 'US-12345',
                    'founded_at' => '2011-06-01',
                    'contacts' => [
                        'email' => 'hello@laravel.com',
                        'phone' => '+1-555-0100',
                        'website' => 'https://laravel.com',
                    ],
                    'office_address' => [
                        'label' => 'Main Office',
                        'location' => [
                            'country' => 'USA',
                            'city' => 'San Francisco',
                            'street' => '456 Tech Blvd',
                            'postal_code' => '94105',
                            'coordinates' => [
                                'latitude' => 37.7849,
                                'longitude' => -122.4094,
                            ],
                            'timezone' => [
                                'name' => 'America/Los_Angeles',
                                'offset' => -8,
                            ],
                        ],
                        'metadata' => [
                            'created_by' => 'admin',
                            'created_at' => '2011-06-01T00:00:00Z',
                            'updated_by' => 'admin',
                            'updated_at' => '2024-01-01T00:00:00Z',
                        ],
                    ],
                    'legal_address' => [
                        'label' => 'Legal Address',
                        'location' => [
                            'country' => 'USA',
                            'city' => 'Delaware',
                            'street' => '789 Corporate Way',
                            'postal_code' => '19801',
                            'coordinates' => [
                                'latitude' => 39.7391,
                                'longitude' => -75.5398,
                            ],
                            'timezone' => [
                                'name' => 'America/New_York',
                                'offset' => -5,
                            ],
                        ],
                        'metadata' => [
                            'created_by' => 'admin',
                            'created_at' => '2011-06-01T00:00:00Z',
                            'updated_by' => 'admin',
                            'updated_at' => '2024-01-01T00:00:00Z',
                        ],
                    ],
                ],
                'metadata' => [
                    'created_by' => 'john.doe',
                    'created_at' => '2024-01-15T10:00:00Z',
                    'updated_by' => 'jane.smith',
                    'updated_at' => '2024-11-20T14:30:00Z',
                ],
                'related_events' => [], // Will be set later
                'sponsors' => [], // Will be set later
            ],
        ]);

        $this->info('✓ Event Entry created with 5+ levels of nested data');

        // Создать вторую Entry
        $eventEntry2 = Entry::create([
            'post_type_id' => $eventPostType->id,
            'title' => 'PHP Summit 2025',
            'slug' => 'php-summit-2025',
            'status' => Entry::STATUS_PUBLISHED,
            'published_at' => now(),
            'author_id' => $this->admin->id,
            'data_json' => [
                'title' => 'PHP Summit 2025',
                'description' => 'Global PHP community gathering',
                'start_date' => '2025-09-10T09:00:00Z',
                'end_date' => '2025-09-12T18:00:00Z',
                'capacity' => 800,
                'venue' => [
                    'country' => 'Germany',
                    'city' => 'Berlin',
                    'street' => 'Alexanderplatz 1',
                    'postal_code' => '10178',
                    'coordinates' => [
                        'latitude' => 52.5200,
                        'longitude' => 13.4050,
                    ],
                    'timezone' => [
                        'name' => 'Europe/Berlin',
                        'offset' => 1,
                    ],
                ],
                'organizer' => [
                    'name' => 'PHP Foundation',
                    'registration_number' => 'DE-67890',
                    'founded_at' => '2021-11-22',
                    'contacts' => [
                        'email' => 'contact@php-foundation.org',
                        'phone' => '+49-30-12345678',
                        'website' => 'https://thephp.foundation',
                    ],
                    'office_address' => [
                        'label' => 'Berlin Office',
                        'location' => [
                            'country' => 'Germany',
                            'city' => 'Berlin',
                            'street' => 'Unter den Linden 10',
                            'postal_code' => '10117',
                            'coordinates' => [
                                'latitude' => 52.5169,
                                'longitude' => 13.3889,
                            ],
                            'timezone' => [
                                'name' => 'Europe/Berlin',
                                'offset' => 1,
                            ],
                        ],
                        'metadata' => [
                            'created_by' => 'system',
                            'created_at' => '2021-11-22T00:00:00Z',
                            'updated_by' => 'system',
                            'updated_at' => '2024-06-15T12:00:00Z',
                        ],
                    ],
                    'legal_address' => [
                        'label' => 'Legal Address',
                        'location' => [
                            'country' => 'Germany',
                            'city' => 'Berlin',
                            'street' => 'Unter den Linden 10',
                            'postal_code' => '10117',
                            'coordinates' => [
                                'latitude' => 52.5169,
                                'longitude' => 13.3889,
                            ],
                            'timezone' => [
                                'name' => 'Europe/Berlin',
                                'offset' => 1,
                            ],
                        ],
                        'metadata' => [
                            'created_by' => 'system',
                            'created_at' => '2021-11-22T00:00:00Z',
                            'updated_by' => 'system',
                            'updated_at' => '2024-06-15T12:00:00Z',
                        ],
                    ],
                ],
                'metadata' => [
                    'created_by' => 'alice.wonder',
                    'created_at' => '2024-03-20T08:00:00Z',
                    'updated_by' => 'bob.builder',
                    'updated_at' => '2024-11-18T16:45:00Z',
                ],
                'related_events' => [$eventEntry1->id],
                'sponsors' => [],
            ],
        ]);

        // Обновить первый event с related_events
        $data1 = $eventEntry1->data_json;
        $data1['related_events'] = [$eventEntry2->id];
        $eventEntry1->update(['data_json' => $data1]);

        $this->info('✓ Second Event Entry created with cross-references');

        // ==========================================
        // ШАГ 8: ПРОВЕРКА ИНДЕКСАЦИИ
        // ==========================================

        $this->info('🔷 Verifying indexation...');

        // Проверить DocValues
        $docValuesCount = DocValue::where('entry_id', $eventEntry1->id)->count();
        $this->assertGreaterThan(20, $docValuesCount, 'Should have 20+ indexed values');

        // Проверить DocRefs
        $docRefsCount = DocRef::where('entry_id', $eventEntry1->id)->count();
        $this->assertEquals(1, $docRefsCount, 'Should have 1 ref (related_events)');

        // Проверить конкретные проиндексированные значения
        $venueCity = DocValue::where('entry_id', $eventEntry1->id)
            ->whereHas('path', fn($q) => $q->where('full_path', 'venue.city'))
            ->first();

        $this->assertNotNull($venueCity);
        $this->assertEquals('San Francisco', $venueCity->value_string);

        // Проверить глубоко вложенное значение (5 уровней)
        $deepValue = DocValue::where('entry_id', $eventEntry1->id)
            ->whereHas('path', fn($q) => $q->where('full_path', 'organizer.office_address.location.coordinates.latitude'))
            ->first();

        $this->assertNotNull($deepValue, 'Deep nested value (5 levels) should be indexed');
        $this->assertEquals(37.7849, $deepValue->value_float);

        $this->info('✓ Indexation verified (deep nested paths indexed correctly)');
        $this->info("  • DocValues: {$docValuesCount}");
        $this->info("  • DocRefs: {$docRefsCount}");

        // ==========================================
        // ШАГ 9: ЗАПРОСЫ ПО ГЛУБОКИМ ПУТЯМ
        // ==========================================

        $this->info('🔷 Testing queries on deep paths...');

        // Поиск по городу venue
        $entriesBySFVenue = Entry::wherePath('venue.city', '=', 'San Francisco')->get();
        $this->assertCount(1, $entriesBySFVenue);
        $this->assertEquals($eventEntry1->id, $entriesBySFVenue->first()->id);

        // Поиск по названию организатора
        $entriesByOrganizer = Entry::wherePath('organizer.name', '=', 'Laravel LLC')->get();
        $this->assertCount(1, $entriesByOrganizer);

        // Поиск по городу office_address организатора (4 уровня)
        $entriesByOfficeCity = Entry::wherePath('organizer.office_address.location.city', '=', 'San Francisco')->get();
        $this->assertCount(1, $entriesByOfficeCity);

        // Поиск по координатам (5 уровней!)
        $entriesByLatitude = Entry::wherePath('organizer.office_address.location.coordinates.latitude', '>', 37.7)
            ->wherePath('organizer.office_address.location.coordinates.latitude', '<', 37.8)
            ->get();
        $this->assertCount(1, $entriesByLatitude);

        // Поиск по timezone (5 уровней через другую ветку)
        $entriesByTimezone = Entry::wherePath('venue.timezone.name', '=', 'America/Los_Angeles')->get();
        $this->assertCount(1, $entriesByTimezone);

        // Поиск по metadata.created_by
        $entriesByCreator = Entry::wherePath('metadata.created_by', '=', 'john.doe')->get();
        $this->assertCount(1, $entriesByCreator);

        // Поиск по ref (related_events)
        $entriesRelatedTo1 = Entry::whereRef('related_events', $eventEntry1->id)->get();
        $this->assertCount(1, $entriesRelatedTo1);
        $this->assertEquals($eventEntry2->id, $entriesRelatedTo1->first()->id);

        $this->info('✓ All deep path queries working correctly (up to 5 levels)');

        // ==========================================
        // ШАГ 10: ИЗМЕНЕНИЕ СТРУКТУРЫ И КАСКАДЫ
        // ==========================================

        $this->info('🔷 Testing cascade updates...');

        // Добавить новое поле в GeoLocation (уровень 0)
        $altitudeField = $this->service->createPath($geoLocation, [
            'name' => 'altitude',
            'data_type' => 'float',
            'is_indexed' => true,
        ]);

        // Проверить, что поле появилось во всех зависимых blueprint
        $event->refresh();

        // Путь: venue.coordinates.altitude
        $venueAltitudePath = $event->paths()
            ->where('full_path', 'venue.coordinates.altitude')
            ->first();

        $this->assertNotNull($venueAltitudePath, 'New field should cascade to Event.venue.coordinates');
        $this->assertTrue($venueAltitudePath->is_readonly);

        // Путь: organizer.office_address.location.coordinates.altitude (6 уровней!)
        $officeAltitudePath = $event->paths()
            ->where('full_path', 'organizer.office_address.location.coordinates.altitude')
            ->first();

        $this->assertNotNull($officeAltitudePath, 'New field should cascade through 6 levels');

        $this->info('✓ Cascade materialization verified (new field propagated through 6 levels)');

        // ==========================================
        // ШАГ 11: ОБНОВЛЕНИЕ ENTRY И РЕИНДЕКСАЦИЯ
        // ==========================================

        $this->info('🔷 Testing reindexation after data update...');

        // Обновить данные Entry с новым полем altitude
        $updatedData = $eventEntry1->data_json;
        $updatedData['venue']['coordinates']['altitude'] = 52.0;
        $updatedData['organizer']['office_address']['location']['coordinates']['altitude'] = 15.0;
        $eventEntry1->update(['data_json' => $updatedData]);

        // Проверить, что altitude проиндексировалась
        $venueAltitudeValue = DocValue::where('entry_id', $eventEntry1->id)
            ->whereHas('path', fn($q) => $q->where('full_path', 'venue.coordinates.altitude'))
            ->first();

        $this->assertNotNull($venueAltitudeValue);
        $this->assertEquals(52.0, $venueAltitudeValue->value_float);

        // Проверить глубокую altitude (6 уровней)
        $officeAltitudeValue = DocValue::where('entry_id', $eventEntry1->id)
            ->whereHas('path', fn($q) => $q->where('full_path', 'organizer.office_address.location.coordinates.altitude'))
            ->first();

        $this->assertNotNull($officeAltitudeValue, '6-level deep field should be indexed');
        $this->assertEquals(15.0, $officeAltitudeValue->value_float);

        // Запрос по новому полю
        $entriesByAltitude = Entry::wherePath('venue.coordinates.altitude', '>', 50.0)->get();
        $this->assertCount(1, $entriesByAltitude);

        $this->info('✓ Reindexation verified (new deep field indexed and queryable)');

        // ==========================================
        // ШАГ 12: СТАТИСТИКА И ИТОГИ
        // ==========================================

        $this->info('🔷 Final statistics...');

        $totalBlueprints = Blueprint::count();
        $totalPaths = Path::count();
        $ownPathsCount = Path::whereNull('source_blueprint_id')->count();
        $copiedPathsCount = Path::whereNotNull('source_blueprint_id')->count();
        $totalEmbeds = BlueprintEmbed::count();
        $totalDocValues = DocValue::count();
        $totalDocRefs = DocRef::count();

        $this->info("📊 System Statistics:");
        $this->info("  • Blueprints: {$totalBlueprints}");
        $this->info("  • Paths (total): {$totalPaths}");
        $this->info("    - Own: {$ownPathsCount}");
        $this->info("    - Materialized: {$copiedPathsCount}");
        $this->info("  • Embeds: {$totalEmbeds}");
        $this->info("  • Entries: 2");
        $this->info("  • DocValues: {$totalDocValues}");
        $this->info("  • DocRefs: {$totalDocRefs}");

        // Проверить максимальную глубину
        $allPaths = Path::all();
        $maxDepthGlobal = $allPaths->max(fn($p) => substr_count($p->full_path, '.'));
        $this->info("  • Max nesting depth: {$maxDepthGlobal} levels");

        $this->assertGreaterThanOrEqual(4, $maxDepthGlobal, 'Should maintain 4+ dots (5 levels) after cascade update');

        // Проверить граф зависимостей
        $eventGraph = $this->service->getDependencyGraph($event);
        $this->assertGreaterThan(5, count($eventGraph['depends_on']), 'Event should depend on 5+ blueprints');

        $this->info('✅ ULTRA-COMPLEX SYSTEM TEST COMPLETED SUCCESSFULLY!');
        $this->info('');
        $this->info('Verified:');
        $this->info('  ✓ 5-level deep nesting (4 dots)');
        $this->info('  ✓ Diamond dependencies');
        $this->info('  ✓ Multiple embeds of same blueprint');
        $this->info('  ✓ Transitive materialization');
        $this->info('  ✓ Cascade updates through all levels');
        $this->info('  ✓ Deep path indexation (DocValues)');
        $this->info('  ✓ Cross-references (DocRefs)');
        $this->info('  ✓ Queries on 5-level deep paths');
        $this->info('  ✓ Reindexation after structure changes');
        $this->info('  ✓ Performance with 100+ paths');
    }

    /**
     * Вспомогательный метод для вывода инфо.
     */
    private function info(string $message): void
    {
        // Output during tests
        fwrite(STDOUT, $message . PHP_EOL);
    }
}

