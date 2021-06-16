<?php
namespace App\Database;

use \stdClass;
use \DOMXPath;
use \XMLReader;
use \DOMDocument;

/**
 * Class XmlParser
 * @package App\Console\Tasks\Feed\Reader
 */
class XmlParser
{
    /**
     * @var \XMLReader
     */
    protected $xmlReader;

    /**
     * @var \stdClass
     */
    protected $rootNode;

    /**
     * @var bool
     */
    protected $ignoreCommentNodes = true;


    public function __construct($ignoreCommentNodes = true)
    {
        $this->xmlReader = new XMLReader();
        $this->ignoreCommentNodes = $ignoreCommentNodes;
    }

    /**
     * @param string $file
     * @param null $encoding
     * @param int $options A bitmask of the LIBXML_*
     * @return bool
     */
    public function loadFile(string $file, $encoding = null, $options = 0) : bool
    {
        if($this->xmlReader->open($file, $encoding, $options))
        {
            // Fetch Root-Node
            $this->parseRootNode();

            return true;
        }

        return false;
    }

    /**
     * @param string $xml
     * @param null $encoding
     * @param int $options A bitmask of the LIBXML_*
     * @return bool
     */
    public function loadXML(string $xml, $encoding = null, $options = 0) : bool
    {
        if($this->xmlReader->XML($xml, $encoding, $options))
        {
            // Fetch Root-Node
            $this->parseRootNode();

            return true;
        }

        return false;
    }

    protected function parseRootNode()
    {
        $this->rootNode = $this->fetch();
    }

    public function getRootNode()
    {
        return $this->rootNode;
    }

    /**
     * @return \stdClass
     */
    public function fetch() : stdClass
    {
        $node = new \stdClass();

        // Skip Comment-Nodes with ease
        if($this->ignoreCommentNodes)
        {
            while ($this->xmlReader->read() && in_array($this->xmlReader->name, ['#comment']))
            {}
        }

        $this->xmlReader->moveToElement();
        $node->{'attributes'} = $this->getNodeAttributes();
        $node->{'domElement'} = $this->xmlReader->expand();

        return $node;
    }

    /**
     * @return \DOMDocument
     */
    public function fetchAsDomDocument() : DOMDocument
    {
        $doc = new DOMDocument();
        $doc->importNode($this->xmlReader->expand());

        return $doc;
    }

    /**
     * @return \stdClass
     */
    protected function getNodeAttributes(string $matchName = '') : \stdClass
    {
        $this->xmlReader->moveToElement();

        $attributes = new \stdClass();

        $attributes->{'length'} = empty($matchName) ? $this->xmlReader->attributeCount : 0;

        $count = 0;
        if($this->xmlReader->hasAttributes)
        {
            $this->xmlReader->moveToFirstAttribute();

            do
            {
                if(!empty($matchName))
                {
                    if (strpos($this->xmlReader->name, $matchName) !== false)
                    {
                        $count++;
                        $attributes->{ $this->xmlReader->name } = $this->xmlReader->value;
                    }

                    continue;
                }

                $attributes->{ $this->xmlReader->name } = $this->xmlReader->value;
            }
            while ($this->xmlReader->moveToNextAttribute());
        }

        if(!empty($matchName))
        {
            $attributes->{'length'} = $count;
        }

        return $attributes;
    }
}
