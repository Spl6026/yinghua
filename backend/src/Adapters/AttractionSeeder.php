<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use RuntimeException;
use Yinghua\Contracts\Transactions;
use Yinghua\DTO\AttractionInput;
use Yinghua\DTO\ClientContext;
use Yinghua\Repositories\AttractionRepository;
use Yinghua\Repositories\Database;
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\Row;

/** [E-18] 僅由明確 content:seed-demo 命令匯入原始交付，不覆蓋已有內容。 */
class AttractionSeeder
{
    public function __construct(private readonly Database $database, private readonly AttractionRepository $attractions, private readonly Transactions $transactions) {}

    public function seed(string $path): void
    {
        $items = $this->source($path);
        $this->transactions->run(function () use ($items): void {
            foreach ($items as $item) {
                if (!is_array($item) || !is_int($item['legacyId'] ?? null) || !is_array($item['fields'] ?? null)
                    || !is_string($item['image'] ?? null) || !is_string($item['imageAlt'] ?? null)) {
                    throw new RuntimeException('Invalid demo record.');
                }
                $legacy = $item['legacyId'];
                if ($legacy < 1 || $legacy > 6 || !str_starts_with($item['image'], 'https://images.unsplash.com/')) {
                    throw new RuntimeException('Unexpected demo source.');
                }
                if ($this->database->row('SELECT id FROM attractions WHERE legacy_id=:legacy', ['legacy' => $legacy]) !== null) {
                    continue;
                }
                $fields = [];
                foreach ($item['fields'] as $key => $value) {
                    if (is_string($key)) {
                        $fields[$key] = $value;
                    }
                }
                $id = sprintf('03000000-0000-4000-8000-%012d', $legacy);
                $this->attractions->save($id, AttractionInput::fromArray($fields, true), true);
                $this->database->execute(
                    "UPDATE attractions SET legacy_id=:legacy,external_image_url=:image,external_image_alt=:alt,status='published' WHERE id=:id",
                    ['id' => $id,'legacy' => $legacy,'image' => $item['image'],'alt' => $item['imageAlt']],
                );
            }
        });
    }

    /** [U-05／R3-02] 明確維護命令；只補仍屬原始展示內容且尚無標籤的筆數。
     * @return list<array{id:string,name:string,label:string}>
     */
    public function backfillLabels(string $path, bool $apply, string $actorId, EventRepository $events, ClientContext $client): array
    {
        $items = $this->source($path);
        return $this->transactions->run(function () use ($items, $apply, $actorId, $events, $client): array {
            if ($apply && $this->database->row("SELECT id FROM admin_users WHERE id=:id AND is_root=1 AND status='active' AND deleted_at IS NULL FOR UPDATE", ['id' => $actorId]) === null) {
                throw new RuntimeException('Active fixed administrator required for maintenance.');
            }
            $changes = [];
            foreach ($items as $item) {
                if (!is_array($item) || !is_int($item['legacyId'] ?? null) || !is_array($item['fields'] ?? null)
                    || !is_string($item['fields']['name'] ?? null) || !is_string($item['fields']['recommendationLabel'] ?? null)
                    || !is_string($item['image'] ?? null)) {
                    throw new RuntimeException('Invalid controlled label source.');
                }
                $label = $item['fields']['recommendationLabel'];
                if ($item['legacyId'] < 1 || $item['legacyId'] > 6 || $label === '' || mb_strlen($label) > 80) {
                    throw new RuntimeException('Unexpected demo label.');
                }
                $values = $this->database->row(
                    "SELECT id,name,version,status FROM attractions WHERE legacy_id=:legacy AND name=:name AND external_image_url=:image AND deleted_at IS NULL AND recommendation_label=''" . ($apply ? ' FOR UPDATE' : ''),
                    ['legacy' => $item['legacyId'],'name' => $item['fields']['name'],'image' => $item['image']],
                );
                if ($values === null) {
                    continue;
                }
                $row = new Row($values);
                if ($apply) {
                    $this->database->execute('UPDATE attractions SET recommendation_label=:label,version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=:id', ['label' => $label,'id' => $row->string('id')]);
                    $events->content($actorId, 'attraction-demo-label-backfill', 'attraction', $row->string('id'), $row->integer('version'), $row->integer('version') + 1, $row->string('status'), $client);
                }
                $changes[] = ['id' => $row->string('id'),'name' => $row->string('name'),'label' => $label];
            }
            return $changes;
        });
    }

    /** @return list<mixed> */
    private function source(string $path): array
    {
        $text = file_get_contents($path);
        $items = $text === false ? null : json_decode($text, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($items) || !array_is_list($items)) {
            throw new RuntimeException('Invalid controlled demo source.');
        }
        return $items;
    }
}
