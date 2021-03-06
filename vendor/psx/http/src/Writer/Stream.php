<?php
/*
 * PSX is a open source PHP framework to develop RESTful APIs.
 * For the current version and informations visit <http://phpsx.org>
 *
 * Copyright 2010-2018 Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace PSX\Http\Writer;

use PSX\Http\ResponseInterface;
use PSX\Http\StreamInterface;

/**
 * Stream
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    http://phpsx.org
 */
class Stream extends Writer
{
    /**
     * @var \PSX\Http\StreamInterface
     */
    protected $data;

    /**
     * @param \PSX\Http\StreamInterface $stream
     * @param string $contentType
     */
    public function __construct(StreamInterface $stream, $contentType = 'application/octet-stream')
    {
        parent::__construct($stream, $contentType);
    }

    /**
     * @inheritdoc
     */
    public function writeTo(ResponseInterface $response)
    {
        $response->setHeader('Content-Type', $this->contentType);
        $response->getBody()->write($this->data->__toString());
    }
}
