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

namespace PSX\Schema\Parser\JsonSchema;

use PSX\Http\Client\ClientInterface;
use PSX\Schema\PropertyInterface;
use PSX\Schema\PropertyType;
use PSX\Uri\Uri;
use PSX\Uri\UriResolver;
use RuntimeException;

/**
 * RefResolver
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    http://phpsx.org
 */
class RefResolver
{
    protected $resolver;
    protected $documents;
    protected $resolvers;
    protected $objects;

    public function __construct(UriResolver $uriResolver = null)
    {
        $this->resolver  = $uriResolver ?: new UriResolver();
        $this->documents = [];
        $this->resolvers = [];
        $this->objects   = [];
    }

    public function setRootDocument(Document $document)
    {
        $this->documents = [$document];
    }

    public function addResolver($scheme, ResolverInterface $resolver)
    {
        $this->resolvers[$scheme] = $resolver;
    }

    /**
     * Resolves an $ref to an property
     *
     * @param \PSX\Schema\Parser\JsonSchema\Document $document
     * @param \PSX\Uri\Uri $ref
     * @param string $name
     * @param integer $depth
     * @param \PSX\Schema\PropertyInterface $property
     * @return \PSX\Schema\PropertyInterface
     */
    public function resolve(Document $document, Uri $ref, $name, $depth, PropertyInterface $property = null)
    {
        $uri = $this->resolver->resolve($document->getBaseUri(), $ref);

        if (isset($this->objects[$uri->toString()])) {
            return $this->objects[$uri->toString()];
        }

        if ($property === null) {
            $property = new PropertyType();
            $property->setRef($uri->toString());
        }

        $this->objects[$uri->toString()] = $property;

        $doc  = $this->getDocument($uri, $document);
        $doc->getProperty($uri->getFragment(), $name, $depth, $property);

        return $property;
    }

    /**
     * Extracts an array part from the document
     *
     * @param \PSX\Schema\Parser\JsonSchema\Document $document
     * @param \PSX\Uri\Uri $ref
     * @return array
     */
    public function extract(Document $document, Uri $ref)
    {
        $uri     = $this->resolver->resolve($document->getBaseUri(), $ref);
        $doc     = $this->getDocument($uri, $document);
        $result  = $doc->pointer($uri->getFragment());
        $baseUri = $doc->getBaseUri();

        // the extracted fragment gets merged into the root document so we must
        // resolve all $ref keys to the base uri so that the root document knows
        // where to find the $ref values
        array_walk_recursive($result, function (&$item, $key) use ($baseUri) {
            if ($key == '$ref') {
                $item = $this->resolver->resolve($baseUri, new Uri($item))->toString();
            }
        });

        return $result;
    }

    /**
     * @param \PSX\Uri\Uri $uri
     * @param \PSX\Schema\Parser\JsonSchema\Document $sourceDocument
     * @return \PSX\Schema\Parser\JsonSchema\Document
     */
    protected function getDocument(Uri $uri, Document $sourceDocument)
    {
        // check whether we have already a document assigned to the base path
        $document = $this->getDocumentById($uri);

        if ($document instanceof Document && $document->canResolve($uri)) {
            return $document;
        }

        if (isset($this->resolvers[$uri->getScheme()])) {
            $document = $this->resolvers[$uri->getScheme()]->resolve($uri, $sourceDocument, $this);

            if ($document instanceof Document) {
                $this->documents[] = $document;

                return $document;
            } else {
                throw new RuntimeException('Could not resolve uri ' . $uri->toString());
            }
        } else {
            throw new RuntimeException('Unknown protocol scheme ' . $uri->getScheme());
        }
    }

    protected function getDocumentById(Uri $uri)
    {
        $key = $this->getKey($uri);

        foreach ($this->documents as $document) {
            if ($key == $this->getKey($document->getBaseUri())) {
                return $document;
            } elseif ($document->getSource() != null && $key == $this->getKey($document->getSource())) {
                return $document;
            }
        }

        return null;
    }

    protected function getKey(Uri $uri)
    {
        return $uri->getScheme() . '-' . $uri->getHost() . '-' . $uri->getPath();
    }

    public static function createDefault(ClientInterface $httpClient = null)
    {
        $resolver = new self();
        $resolver->addResolver('file', new Resolver\File());

        if ($httpClient !== null) {
            $httpResolver = new Resolver\Http($httpClient);

            $resolver->addResolver('http', $httpResolver);
            $resolver->addResolver('https', $httpResolver);
        }

        return $resolver;
    }
}
