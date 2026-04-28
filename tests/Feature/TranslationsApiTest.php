<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TranslationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_translation_persists_key_values_and_tags(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/translations', [
            'key' => 'welcome.title',
            'translations' => ['en' => 'Welcome', 'fr' => 'Bienvenue'],
            'tags' => ['web', 'marketing'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('key', 'welcome.title')
            ->assertJsonPath('translations.en', 'Welcome')
            ->assertJsonPath('translations.fr', 'Bienvenue');

        $this->assertDatabaseHas('translation_keys', ['key' => 'welcome.title']);
        $this->assertDatabaseCount('translation_values', 2);
        $this->assertDatabaseCount('tags', 2);
    }

    public function test_create_translation_requires_authentication(): void
    {
        $this->postJson('/api/translations', [
            'key' => 'x',
            'translations' => ['en' => 'X'],
        ])->assertUnauthorized();
    }

    public function test_update_translation_replaces_values_and_tags(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $key = TranslationKey::factory()->create(['key' => 'cta.signup']);
        TranslationValue::factory()->create([
            'translation_key_id' => $key->id,
            'locale' => 'en',
            'content' => 'Sign up',
        ]);
        $key->tags()->sync([Tag::factory()->create(['name' => 'old'])->id]);

        $response = $this->putJson('/api/translations/cta.signup', [
            'translations' => ['en' => 'Join now', 'fr' => 'Rejoignez'],
            'tags' => ['cta'],
        ]);

        $response->assertOk()
            ->assertJsonPath('translations.en', 'Join now')
            ->assertJsonPath('translations.fr', 'Rejoignez')
            ->assertJsonPath('tags.0', 'cta');

        $this->assertDatabaseHas('translation_values', [
            'translation_key_id' => $key->id,
            'locale' => 'en',
            'content' => 'Join now',
        ]);
    }

    public function test_search_filters_by_key_locale_and_tag(): void
    {
        $matching = TranslationKey::factory()->create(['key' => 'auth.login.title']);
        TranslationValue::factory()->create([
            'translation_key_id' => $matching->id,
            'locale' => 'en',
            'content' => 'Sign in',
        ]);
        $matching->tags()->sync([Tag::factory()->create(['name' => 'auth'])->id]);

        $other = TranslationKey::factory()->create(['key' => 'home.title']);
        TranslationValue::factory()->create([
            'translation_key_id' => $other->id,
            'locale' => 'en',
            'content' => 'Home',
        ]);

        $response = $this->getJson('/api/translations?key=auth&locale=en&tags[]=auth');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'auth.login.title');
    }

    public function test_export_returns_translations_grouped_by_locale(): void
    {
        $hello = TranslationKey::factory()->create(['key' => 'greeting.hello']);
        TranslationValue::factory()->create([
            'translation_key_id' => $hello->id,
            'locale' => 'en',
            'content' => 'Hello',
        ]);
        TranslationValue::factory()->create([
            'translation_key_id' => $hello->id,
            'locale' => 'fr',
            'content' => 'Bonjour',
        ]);

        $response = $this->getJson('/api/translations/export');

        $response->assertOk()->assertJson([
            'en' => ['greeting.hello' => 'Hello'],
            'fr' => ['greeting.hello' => 'Bonjour'],
        ]);
    }
}
