<?php
/*
 * K-Load v2 (https://demo.maddela.org/k-load/).
 *
 * @link      https://www.maddela.org
 * @link      https://github.com/kanalumaddela/k-load-v2
 *
 * @author    kanalumaddela <git@maddela.org>
 * @copyright Copyright (c) 2018-2021 kanalumaddela
 * @license   MIT
 */

namespace KLoad\Exceptions;

use Throwable;

class InvalidToken extends HttpException
{
    public function __construct(int $statusCode = 500, string $message = '', Throwable $previous = null, array $headers = [], ?int $code = 0)
    {
        $statusCode = 401;
        $message = 'CSRF token invalid/not given';

        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }
}
