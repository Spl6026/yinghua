<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\Input;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Services\MediaManagement;
use Yinghua\Services\MediaAccess;
use Yinghua\Models\MediaModule;

class MediaController
{
    public function __construct(private readonly MediaManagement $service, private readonly MediaAccess $access) {}

    public function access(ApiContext $context): Response
    {
        $path = parse_url($context->request->headers['x-original-uri'] ?? '', PHP_URL_PATH);

        return new Response(is_string($path) && $this->access->allows($path, $context->sessionToken) ? 204 : 403);
    }

    public function upload(ApiContext $context): Response
    {
        $input = new Input($context->request->form);
        $input->only(['action','alt','source','license','module']);
        $module = MediaModule::parse($input->optionalText('module') ?: 'attractions');
        $action = $input->text('action');
        if (!in_array($action, ['create','edit'], true)) {
            throw ApplicationError::invalid('action', '請指定新增或編輯用途。');
        }
        if (array_keys($context->request->files) !== ['image']) {
            throw ApplicationError::invalid('image', '請上傳一張圖片。');
        }
        $asset = $this->service->upload(
            $context->actor(),
            $context->request->files['image'],
            $action,
            $input->text('alt', 1, 255),
            $input->optionalText('source'),
            $input->optionalText('license'),
            $context->client,
            $module,
        );

        return new Response(201, ['media' => $asset->toArray()]);
    }

    public function listing(ApiContext $context): Response
    {
        return new Response(200, $this->service->listing($context->actor(), $context->request->queryInteger('page', 1, 100000), $context->request->queryInteger('pageSize', 20, 100), MediaModule::parse($context->request->queryText('module') ?: 'attractions')));
    }
}
