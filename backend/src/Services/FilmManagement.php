<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\Transactions;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\Film;
use Yinghua\DTO\FilmInput;
use Yinghua\DTO\FilmPage;
use Yinghua\DTO\FilmWriteResult;
use Yinghua\DTO\FilmScreening;
use Yinghua\DTO\ScreeningInput;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Exceptions\ContentConflict;
use Yinghua\Models\Account;
use Yinghua\Models\ContentStatus;
use Yinghua\Models\MediaModule;
use Yinghua\Policies\FilmPosterPolicy;
use Yinghua\Policies\ScreeningStartPolicy;
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\FilmRepository;
use Yinghua\Repositories\FilmScreeningRepository;
use Yinghua\Repositories\MediaRepository;
use Yinghua\Support\Secrets;

/** [S：功能規格 21；U-02；E-25] 電影寫入的授權、版本與發布交易邊界。 */
class FilmManagement
{
    public function __construct(
        private readonly FilmRepository $films,
        private readonly MediaRepository $media,
        private readonly Authorization $authorization,
        private readonly Transactions $transactions,
        private readonly EventRepository $events,
        private readonly ContentPublication $publication,
        private readonly FilmPosterPolicy $posters,
        private readonly FilmScreeningRepository $screenings,
        private readonly ScreeningStartPolicy $starts,
    ) {}

    public function listing(Account $actor, string $q, string $status, int $page, int $size): FilmPage
    {
        $this->authorization->require($actor, 'films', 'view');
        if ($status !== '' && ContentStatus::tryFrom($status) === null) {
            throw ApplicationError::invalid('status', '不支援的狀態。');
        }
        return $this->films->listing($q, $status, $page, $size);
    }

    public function get(Account $actor, string $id): Film
    {
        $this->authorization->require($actor, 'films', 'view');
        return $this->target($id);
    }

    public function save(Account $actor, ?string $id, FilmInput $input, ClientContext $client): FilmWriteResult
    {
        return $this->publication->exclusive(function () use ($actor, $id, $input, $client): FilmWriteResult {
            $creating = $id === null;
            $targetId = $id ?? Secrets::uuid();
            try {
                $film = $this->transactions->run(function () use ($actor, $targetId, $input, $client, $creating): Film {
                    $fresh = $this->authorization->require($actor, 'films', $creating ? 'create' : 'edit', true);
                    $before = $creating ? null : $this->target($targetId, true);
                    if ($before !== null && $before->version !== $input->version) {
                        throw new ApplicationError('CONFLICT', '資料已被其他人修改，請重新載入。');
                    }
                    $input = $input->withDefaultSlug($before === null ? 'film-' . str_replace('-', '', $targetId) : $before->slug);
                    $posterId = array_key_exists('poster_media_id', $input->fields) ? $input->fields['poster_media_id'] : ($before?->values['posterMediaId'] ?? null);
                    $poster = is_string($posterId) ? $this->media->find($posterId, MediaModule::Films) : null;
                    $this->posters->assertValid($before, $input, $poster);
                    // [U-16／E-25] 草稿占用開始時間；下架釋放。恢復發布即使省略場次也須重新比較。
                    $existingRows = $before === null ? [] : $before->screenings;
                    $rows = $input->screenings ?? array_map(static fn(FilmScreening $row): ScreeningInput => ScreeningInput::fromArray(['id' => $row->id,'startsAt' => $row->startsAt,'ticketUrl' => $row->ticketUrl]), $existingRows);
                    $this->starts->assertDistinct($rows);
                    $status = $input->status === null ? ($before === null ? ContentStatus::Draft->value : $before->status) : $input->status->value;
                    if ($status !== ContentStatus::Archived->value) {
                        $this->screenings->assertAvailable($targetId, $rows);
                    }
                    $this->films->save($targetId, $input, $creating, $fresh->id);
                    if ($input->screenings !== null) {
                        $this->screenings->replace($targetId, $rows);
                    }
                    $updated = $this->target($targetId);
                    $this->events->content($fresh->id, 'film-' . ($creating ? 'create' : 'update'), 'film', $targetId, $before?->version, $updated->version, $updated->status, $client);
                    return $updated;
                });
            } catch (ContentConflict) {
                throw new ApplicationError('CONFLICT', '網址識別已使用或保留於歷史網址，請改用其他識別。', ['slug' => '此識別已使用。']);
            }
            return new FilmWriteResult($film, $this->publication->synchronize());
        });
    }

    public function delete(Account $actor, string $id, int $version, ClientContext $client): FilmWriteResult
    {
        return $this->publication->exclusive(function () use ($actor, $id, $version, $client): FilmWriteResult {
            $this->transactions->run(function () use ($actor, $id, $version, $client): void {
                $fresh = $this->authorization->require($actor, 'films', 'delete', true);
                $before = $this->target($id, true);
                if ($before->version !== $version) {
                    throw new ApplicationError('CONFLICT', '資料已變更，請重新載入。');
                }
                $this->films->delete($id);
                $this->events->content($fresh->id, 'film-delete', 'film', $id, $version, $version + 1, 'archived', $client);
            });
            return new FilmWriteResult(null, $this->publication->synchronize());
        });
    }

    public function retry(Account $actor): bool
    {
        $this->authorization->require($actor, 'films', 'edit');
        return $this->publication->retry();
    }

    private function target(string $id, bool $lock = false): Film
    {
        $film = $this->films->find($id, $lock);
        if ($film === null || $film->deletedAt !== null) {
            throw new ApplicationError('NOT_FOUND', '找不到此電影。');
        }
        return $film;
    }
}
