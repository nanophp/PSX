<?php
/*
 * PSX is a open source PHP framework to develop RESTful APIs.
 * For the current version and informations visit <http://phpsx.org>
 *
 * Copyright 2010-2017 Christoph Kappestein <christoph.kappestein@gmail.com>
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

namespace PSX\Schema\Parser\JsonSchema\Resolver;

use PSX\Json\Parser;
use PSX\Schema\Parser\JsonSchema\Document;
use PSX\Schema\Parser\JsonSchema\RefResolver;
use PSX\Schema\Parser\JsonSchema\ResolverInterface;
use PSX\Uri\Uri;
use RuntimeException;

/**
 * File
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    http://phpsx.org
 */
class File implements ResolverInterface
{
    public function resolve(Uri $uri, Document $source, RefResolver $resolver)
    {
        if ($source->isRemote()) {
            throw new RuntimeException('Can not resolve file scheme from remote source');
        }

        $path = str_replace('/', DIRECTORY_SEPARATOR, ltrim($uri->getPath(), '/'));
        $path = $source->getBasePath() !== null ? $source->getBasePath() . DIRECTORY_SEPARATOR . $path : $path;

        if (is_file($path)) {
            $basePath = pathinfo($path, PATHINFO_DIRNAME);
            $schema   = file_get_contents($path);
            $data     = Parser::decode($schema, true);
            $document = new Document($data, $resolver, $basePath, $uri);

            return $document;
        } else {
            throw new RuntimeException('Could not load external schema ' . $path);
        }
    }
}
