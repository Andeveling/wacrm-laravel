<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Contact;
use App\Models\ContactCustomValue;
use App\Models\ContactTag;
use App\Models\CustomField;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Contactos del equipo demo, cada uno con etiquetas y los tres campos
 * personalizados poblados. Depende de DemoTagsSeeder y
 * DemoCustomFieldsSeeder.
 */
class DemoContactsSeeder extends Seeder
{
    private const NAMES = [
        'María González', 'Carlos Ramírez', 'Ana Martínez', 'Luis Rodríguez',
        'Sofía Hernández', 'Diego López', 'Valentina Díaz', 'Andrés Pérez',
        'Camila Sánchez', 'Sebastián Castro', 'Isabella Torres', 'Mateo Vargas',
        'Lucía Mendoza', 'Santiago Ruiz', 'Daniela Morales',
    ];

    private const VIP_COUNT = 3;

    private const CIUDADES = ['Bogotá', 'Medellín', 'Cali', 'Barranquilla', 'Cartagena'];

    private const SECTORES = ['Retail', 'Servicios', 'Tecnología', 'Salud', 'Educación'];

    private const COMPANIES = [
        null, 'TechCorp', 'Innovación S.A.', 'Soluciones Ltda.', 'Servicios Colombia',
    ];

    public function run(Account $team, User $owner): void
    {
        if (Contact::where('account_id', $team->id)->exists()) {
            return;
        }

        $tags = Tag::where('account_id', $team->id)->get();
        $customFields = CustomField::where('account_id', $team->id)->get();

        foreach (self::NAMES as $index => $name) {
            $contact = Contact::create([
                'account_id' => $team->id,
                'user_id' => $owner->id,
                'phone' => '+57'.fake()->unique()->numerify('310#######'),
                'name' => $name,
                'email' => fake()->safeEmail(),
                'company' => fake()->randomElement(self::COMPANIES),
            ]);

            $this->attachTags($contact, $tags, isVip: $index >= count(self::NAMES) - self::VIP_COUNT);
            $this->fillCustomValues($contact, $customFields);
        }
    }

    /**
     * Una o dos etiquetas al azar, más "VIP" para los últimos contactos.
     * Todo contacto demo lleva al menos una: uno sin etiquetas no muestra
     * nada en la UI.
     *
     * @param  Collection<int, Tag>  $tags
     */
    private function attachTags(Contact $contact, Collection $tags, bool $isVip): void
    {
        $tagIds = $tags->shuffle()->take(fake()->numberBetween(1, 2))->pluck('id')->all();

        if ($isVip) {
            $vipId = $tags->firstWhere('name', DemoTagsSeeder::VIP)?->id;

            if ($vipId !== null && ! in_array($vipId, $tagIds, true)) {
                $tagIds[] = $vipId;
            }
        }

        foreach ($tagIds as $tagId) {
            ContactTag::create(['contact_id' => $contact->id, 'tag_id' => $tagId]);
        }
    }

    /**
     * @param  Collection<int, CustomField>  $customFields
     */
    private function fillCustomValues(Contact $contact, Collection $customFields): void
    {
        $values = [
            DemoCustomFieldsSeeder::CIUDAD => fake()->randomElement(self::CIUDADES),
            DemoCustomFieldsSeeder::SECTOR => fake()->randomElement(self::SECTORES),
            DemoCustomFieldsSeeder::CUMPLEANOS => fake()->dateTimeBetween('-30 years', 'now')->format('Y-m-d'),
        ];

        foreach ($values as $fieldName => $value) {
            $field = $customFields->firstWhere('field_name', $fieldName);

            if ($field === null) {
                continue;
            }

            ContactCustomValue::create([
                'contact_id' => $contact->id,
                'custom_field_id' => $field->id,
                'value' => $value,
            ]);
        }
    }
}
