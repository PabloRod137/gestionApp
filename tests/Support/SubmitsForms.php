<?php

namespace Tests\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

/**
 * Envía los formularios de una pantalla tal y como lo haría el navegador: con los campos que la propia vista
 * dibuja (nombres, valores por defecto, campos ocultos y método simulado). Así se detecta cuando una vista y su
 * controlador usan nombres distintos, que es lo que un test que arma las peticiones a mano no ve.
 */
trait SubmitsForms
{
    /** @return array<int, array{action: string, method: string, fields: array, files: array}> */
    protected function formsOn(string $url, array $override = []): array
    {
        $html = $this->get($url)->assertOk()->getContent();
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new DOMXPath($dom);

        $forms = [];
        foreach ($xpath->query('//form') as $form) {
            /** @var DOMElement $form */
            $pairs = [];
            $files = [];
            $method = strtoupper($form->getAttribute('method') ?: 'GET');
            foreach ($xpath->query('.//input|.//select|.//textarea', $form) as $field) {
                /** @var DOMElement $field */
                $name = $field->getAttribute('name');
                if ($name === '' || $field->hasAttribute('disabled')) {
                    continue;
                }
                $tag = $field->tagName;
                $type = strtolower($field->getAttribute('type') ?: 'text');
                $value = $field->getAttribute('value');

                if ($tag === 'select') {
                    $selected = $xpath->query('.//option[@selected]', $field)->item(0)
                        ?? $xpath->query('.//option[string-length(@value) > 0]', $field)->item(0);
                    $value = $selected?->getAttribute('value') ?? '';
                } elseif ($tag === 'textarea') {
                    $value = trim($field->textContent);
                } elseif (in_array($type, ['checkbox', 'radio'], true)) {
                    if (! $field->hasAttribute('checked')) {
                        continue;
                    }
                    $value = $value !== '' ? $value : 'on';
                } elseif ($type === 'file') {
                    if ($field->hasAttribute('required')) {
                        $files[$name] = str_contains($field->getAttribute('accept'), 'image')
                            ? UploadedFile::fake()->image('prueba.jpg')
                            : UploadedFile::fake()->create('prueba.pdf', 20, 'application/pdf');
                    }

                    continue;
                } elseif ($type === 'submit' || $type === 'button') {
                    continue;
                }

                if ($value === '' && $field->hasAttribute('required')) {
                    $value = $this->sampleValue($type, $name);
                }
                $pairs[] = rawurlencode($name).'='.rawurlencode($value);
            }

            parse_str(implode('&', $pairs), $fields);
            $fields = array_replace_recursive($fields, $override);
            if (isset($fields['_method'])) {
                $method = strtoupper($fields['_method']);
            }
            $forms[] = ['action' => $form->getAttribute('action'), 'method' => $method, 'fields' => $fields, 'files' => $files];
        }

        return $forms;
    }

    /** Envía el formulario de $url cuya acción contiene $actionContains. */
    protected function submitForm(string $url, string $actionContains, array $override = [], ?string $method = null): TestResponse
    {
        foreach ($this->formsOn($url, $override) as $form) {
            if (str_contains($form['action'], $actionContains) && ($method === null || $form['method'] === strtoupper($method))) {
                $path = parse_url($form['action'], PHP_URL_PATH);
                $verb = $form['method'] === 'GET' ? 'GET' : 'POST';

                return $this->call($verb, $path, $form['fields'], [], $form['files'], ['HTTP_REFERER' => url($url)]);
            }
        }

        $this->fail("No hay un formulario con «{$actionContains}» en {$url}");
    }

    /** Un valor creíble para un campo obligatorio que la vista deja vacío. */
    protected function sampleValue(string $type, string $name): string
    {
        return match (true) {
            $type === 'email' => 'prueba@example.test',
            $type === 'number' => '1',
            $type === 'date' => now('Europe/Madrid')->addDays(10)->nextWeekday()->toDateString(),
            $type === 'time' => '10:00',
            $type === 'tel' => '600000000',
            $type === 'url' => 'https://example.test',
            $type === 'password' => 'Passw0rd!2026-x',
            str_contains($name, 'nif') => 'B39123456',
            default => 'Texto de prueba',
        };
    }
}
