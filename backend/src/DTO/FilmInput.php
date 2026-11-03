<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use DateTimeImmutable;
use DateTimeZone;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\ContentStatus;
use Yinghua\Support\Uuid;

/** 電影表單輸入，包含場次清單。 */
readonly class FilmInput
{
    /** @param array<string,string|int|bool|null> $fields
     * @param list<ScreeningInput>|null $screenings null 代表保留既有場次
     */
    public function __construct(public array $fields, public ?int $version, public ?ContentStatus $status, public ?array $screenings) {}

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values, bool $creating): self
    {
        $input = new Input($values);
        $input->only(['title','slug','genre','synopsis','posterMediaId','posterUrl','posterAlt','posterSource','posterLicense',
            'ticketUrl','status','publishAt','seoTitle','seoDescription','screenings','version']);
        $fields = ['title' => $input->text('title'), 'slug' => $input->optionalText('slug', 160)];
        if ($fields['slug'] !== '' && (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $fields['slug'])
            || $fields['slug'] === 'resolve-url')) {
            throw ApplicationError::invalid('slug', '請使用未保留的小寫英文、數字與連字號。');
        }
        foreach (['genre' => 255,'synopsis' => 20000,'seoTitle' => 160,'seoDescription' => 500] as $key => $max) {
            if ($creating || array_key_exists($key, $values)) {
                $column = ['seoTitle' => 'seo_title','seoDescription' => 'seo_description'][$key] ?? $key;
                $fields[$column] = $input->optionalText($key, $max);
            }
        }
        foreach (['posterUrl' => 'poster_url','ticketUrl' => 'ticket_url'] as $key => $column) {
            if ($creating || array_key_exists($key, $values)) {
                $fields[$column] = ContentUrl::validate($input->optionalText($key, 2000), $key);
            }
        }
        if ($creating || array_key_exists('posterMediaId', $values)) {
            $id = ($values['posterMediaId'] ?? null) === null ? '' : $input->optionalText('posterMediaId', 36);
            if ($id !== '' && !Uuid::isValid($id)) {
                throw ApplicationError::invalid('posterMediaId', '請選擇有效海報。');
            }
            $fields['poster_media_id'] = $id === '' ? null : $id;
        }
        foreach (['posterAlt' => 'poster_alt','posterSource' => 'poster_source','posterLicense' => 'poster_license'] as $key => $column) {
            if (array_key_exists($key, $values)) {
                $text = $values[$key] === null ? '' : $input->text($key, 0, 255);
                $fields[$column] = $text === '' ? null : $text;
            }
        }
        $status = null;
        if ($creating || array_key_exists('status', $values)) {
            $status = ContentStatus::tryFrom(array_key_exists('status', $values) ? $input->text('status') : 'draft');
            if ($status === null) {
                throw ApplicationError::invalid('status', '請選擇有效發布狀態。');
            }
            $fields['status'] = $status->value;
        }
        if ($creating || array_key_exists('publishAt', $values)) {
            $text = $input->optionalText('publishAt', 30);
            $date = $text === ''
                ? new DateTimeImmutable('now', new DateTimeZone('UTC'))
                : DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $text);
            if ($date === false || ($text !== '' && $date->format('Y-m-d\TH:i:sP') !== $text)) {
                throw ApplicationError::invalid('publishAt', '請提供含時區的有效發布時間。');
            }
            $utc = $date->setTimezone(new DateTimeZone('UTC'));
            if ((int) $utc->format('Y') < 1000 || (int) $utc->format('Y') > 9999) {
                throw ApplicationError::invalid('publishAt', '日期超出支援範圍。');
            }
            $fields['publish_at'] = $utc->format('Y-m-d H:i:s');
        }
        $screenings = $creating ? [] : null;
        if (array_key_exists('screenings', $values)) {
            if (!is_array($values['screenings']) || !array_is_list($values['screenings'])) {
                throw ApplicationError::invalid('screenings', '請提供場次清單。');
            }
            $screenings = [];
            foreach ($values['screenings'] as $index => $row) {
                if (!is_array($row)) {
                    throw ApplicationError::invalid('screenings', '每筆場次須為物件。');
                }
                $object = [];
                foreach ($row as $key => $value) {
                    if (!is_string($key)) {
                        throw ApplicationError::invalid('screenings', '場次欄位名稱無效。');
                    }
                    $object[$key] = $value;
                }
                try {
                    $screenings[] = ScreeningInput::fromArray($object);
                } catch (ApplicationError $error) {
                    $errors = [];
                    foreach ($error->fieldErrors as $key => $message) {
                        $errors['screenings.' . $index . '.' . $key] = $message;
                    }
                    throw new ApplicationError($error->errorCode, $error->getMessage(), $errors);
                }
            }
        }

        return new self($fields, $creating ? null : $input->positiveInt('version'), $status, $screenings);
    }

    public function withDefaultSlug(string $slug): self
    {
        return $this->fields['slug'] !== ''
            ? $this
            : new self([...$this->fields,'slug' => $slug], $this->version, $this->status, $this->screenings);
    }
}
