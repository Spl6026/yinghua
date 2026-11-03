<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\DTO\ClientContext;
use Yinghua\DTO\Film;
use Yinghua\DTO\FilmInput;
use Yinghua\DTO\FilmPage;
use Yinghua\DTO\FilmWriteResult;
use Yinghua\DTO\FilmScreening;
use Yinghua\DTO\ScreeningInput;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Models\ContentStatus;
use Yinghua\Models\MediaModule;
use Yinghua\Policies\FilmPosterPolicy;
use Yinghua\Policies\ScreeningStartPolicy;
use Yinghua\Repositories\FilmRepository;
use Yinghua\Repositories\FilmScreeningRepository;
use Yinghua\Repositories\MediaRepository;
use Yinghua\Exceptions\ErrorCode;
use Yinghua\Support\Uuid;

/** 電影寫入流程：權限檢查、版本比對，以及寫入與發布的交易範圍。 */
class FilmManagement
{
    public function __construct(
        private readonly FilmRepository $films,
        private readonly MediaRepository $media,
        private readonly Authorization $authorization,
        private readonly ContentWriter $writer,
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

    /** 有檢視或編輯權限即可讀取單筆；規格的編輯頁對象是「具 create／edit 權限者」，不另外要求列表檢視權限。 */
    public function get(Account $actor, string $id): Film
    {
        $this->authorization->requireAny($actor, 'films', ['view', 'edit']);
        return $this->target($id);
    }

    public function save(Account $actor, ?string $id, FilmInput $input, ClientContext $client): FilmWriteResult
    {
        [$film, $synchronized] = $this->writer->save(
            $actor,
            'films',
            ['film', 'film'],
            $id,
            $input->version,
            $this->target(...),
            function (?Film $before, string $targetId, Account $fresh) use ($input): Film {
                $input = $input->withDefaultSlug($before === null ? 'film-' . str_replace('-', '', $targetId) : $before->slug);
                $posterId = array_key_exists('poster_media_id', $input->fields)
                    ? $input->fields['poster_media_id']
                    : ($before?->values['posterMediaId'] ?? null);
                $poster = is_string($posterId) ? $this->media->find($posterId, MediaModule::Films) : null;
                $this->posters->assertValid($before, $input, $poster);
                // 草稿也會占用場次開始時間，下架後才釋放；
                // 重新發布時即使沒有送場次，也要用既有場次再檢查一次是否衝突。
                $existingRows = $before === null ? [] : $before->screenings;
                $rows = $input->screenings
                    ?? array_map(static fn(FilmScreening $row): ScreeningInput => ScreeningInput::fromArray(['id' => $row->id,
                        'startsAt' => $row->startsAt,
                        'ticketUrl' => $row->ticketUrl]), $existingRows);
                $this->starts->assertDistinct($rows);
                $status = $input->status === null
                    ? ($before === null ? ContentStatus::Draft->value : $before->status)
                    : $input->status->value;
                if ($status !== ContentStatus::Archived->value) {
                    $this->screenings->assertAvailable($targetId, $rows);
                }
                $this->films->save($targetId, $input, $before === null, $fresh->id);
                if ($input->screenings !== null) {
                    $this->screenings->replace($targetId, $rows);
                }

                return $this->target($targetId);
            },
            $client,
        );
        return new FilmWriteResult($film, $synchronized);
    }

    public function delete(Account $actor, string $id, int $version, ClientContext $client): FilmWriteResult
    {
        $synchronized = $this->writer->delete(
            $actor,
            'films',
            ['film', 'film'],
            $id,
            $version,
            $this->target(...),
            fn(Film $film) => $this->films->delete($film->id),
            $client,
        );
        return new FilmWriteResult(null, $synchronized);
    }

    public function retry(Account $actor): bool
    {
        $this->authorization->require($actor, 'films', 'edit');
        return $this->publication->retry();
    }

    private function target(string $id, bool $lock = false): Film
    {
        $film = Uuid::isValid($id) ? $this->films->find($id, $lock) : null;
        if ($film === null || $film->deletedAt !== null) {
            throw new ApplicationError(ErrorCode::NotFound, '找不到此電影。');
        }
        return $film;
    }
}
