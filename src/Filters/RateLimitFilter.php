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

namespace Myth\Betta\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Myth\Betta\Config\Betta;

class RateLimitFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        /** @phpstan-ignore codeigniter.factoriesClassConstFetch */
        $config    = config(Betta::class);
        $throttler = service('throttler');

        if ($throttler->check($request->getIPAddress(), $config->rateLimitRequests, $config->rateLimitWindow, 1)) {
            return null;
        }

        $response = service('response');
        $accept   = $request->getHeaderLine('Accept');

        if (str_contains($accept, 'application/json')) {
            return $response
                ->setStatusCode(429)
                ->setJSON(['error' => 'Too Many Requests']);
        }

        return $response
            ->setStatusCode(429)
            ->setBody('Too Many Requests. Please wait before submitting again.');
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }
}
