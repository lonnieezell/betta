<?php

declare(strict_types=1);

/**
 * This file is part of Myth/Betta.
 *
 * (c) Lonnie Ezell <lonnieje@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Myth\Betta\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Myth\Betta\Config\Betta;
use Myth\Betta\Enums\CategoryEnum;
use Myth\Betta\Models\FeedbackModel;

class FeedbackController extends Controller
{
    protected $helpers = ['form', 'url'];
    private readonly Betta $config;

    public function __construct()
    {
        /** @phpstan-ignore codeigniter.factoriesClassConstFetch */
        $this->config = config(Betta::class);
    }

    public function index(): string
    {
        if (! $this->config->acceptSubmissions) {
            return $this->renderView('closed');
        }

        return $this->renderView('page', [
            'categories' => CategoryEnum::cases(),
            'submitUrl'  => $this->config->routePrefix . '/submit',
        ]);
    }

    public function submit(): ResponseInterface
    {
        if (! $this->config->acceptSubmissions) {
            return redirect()->to($this->config->routePrefix);
        }

        $rules = [
            'category' => 'permit_empty|in_list[bug,ux,feature,other]',
            'message' => 'required',
        ];

        $isJson = $this->isJsonRequest();

        if (! $this->validate($rules)) {
            if ($isJson) {
                return $this->response
                    ->setStatusCode(422)
                    ->setJSON(['errors' => $this->validator->getErrors()]);
            }

            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $posted     = $this->request->getPost('url_context');
        $urlContext = ($posted !== null && $posted !== '')
            ? $posted
            : $this->request->getHeaderLine('Referer');

        // Strip query string and fragment to avoid storing PII (tokens, emails, etc.)
        $urlContext = substr($urlContext, 0, strcspn($urlContext, '?#'));

        $rawCategory = $this->request->getPost('category');
        $category    = ($rawCategory !== null && $rawCategory !== '') ? $rawCategory : 'other';

        $model = new FeedbackModel();
        $newId = $model->insert([
            'session_id'  => hash('sha256', session_id()),
            'category'    => CategoryEnum::from($category),
            'message'     => $this->request->getPost('message'),
            'url_context' => ($urlContext !== '') ? $urlContext : null,
        ]);
        log_message('info', "betta.feedback: submission #{$newId} received (category={$category})");

        if ($isJson) {
            return $this->response->setJSON(['ok' => true]);
        }

        return redirect()->to($this->config->routePrefix)->with('feedback_success', 'Thank you for your feedback!');
    }

    private function renderView(string $view, array $data = []): string
    {
        $override = APPPATH . 'Views/vendor/betta/' . $view . '.php';
        if (is_file($override)) {
            return view('vendor/betta/' . $view, $data);
        }

        return view('Myth\Betta\Views\\' . $view, $data);
    }

    private function isJsonRequest(): bool
    {
        $accept = $this->request->getHeaderLine('Accept');
        $xhr    = $this->request->getHeaderLine('X-Requested-With');

        return str_contains($accept, 'application/json') || strtolower($xhr) === 'xmlhttprequest';
    }
}
