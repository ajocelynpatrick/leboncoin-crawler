<?php

namespace Lbc\Crawler;

use Lbc\Filter\DefaultSanitizer;
use Lbc\Filter\PriceSanitizer;
use Lbc\Filter\PrixSanitizer;
use Lbc\Parser\AdUrlParser;
use Lbc\Parser\SearchResultUrlParser;
use League\Uri\Schemes\Http;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class SearchResultAdCrawler
 * @package Lbc\Crawler
 */
class SearchResultAdCrawler extends CrawlerAbstract
{
    /**
     * @param $url
     * @return SearchResultUrlParser
     */
    protected function setUrlParser($url)
    {
        $this->url = new AdUrlParser($url);
    }

    /**
     * Return the Ad's ID
     *
     * @return string
     */
    public function getId()
    {
        return $this->url->getId();
    }


    /**
     * Return the title
     *
     * @return string
     */
    public function getTitle()
    {
        return DefaultSanitizer::clean($this->node->filter('h2')->text());
    }

    /**
     * Return the price
     *
     * @return int
     */
    public function getPrice()
    {
        if ($this->node->filter('*[itemprop=price]')->count()) {
            return PrixSanitizer::clean(
                $this->node->filter('*[itemprop=price]')->text()
            );
        }
        return 0;
    }

    /**
     * Return the Ad's URL
     *
     * @return string
     */
    public function getUrl()
    {
        return (string)Http::createFromString($this->url)->withScheme('https');
    }

    /**
     * Return the data and time the ad was created
     *
     * @return string
     */
    public function getCreatedAt()
    {
        $node = $this->node
            ->filter('*[itemprop=availabilityStarts]')
            ->first();

        $date = $node->attr('content');

        $time = $this->getFieldValue($node, 0, function ($value) {
            $value = trim($value);

            return substr($value, strpos($value, ',') + 2);
        });

        return $date . ' ' . $time;
    }

    /**
     * Return the thumb picture url
     *
     * @return null|string
     */
    public function getThumb()
    {
        $image = $this->node
            ->filter('.item_imagePic .lazyload[data-imgsrc]')
            ->first();

        if (0 === $image->count()) {
            return null;
        }

        $src = $image
            ->attr('data-imgsrc');

        return (string)Http::createFromString($src)->withScheme('https');
    }

    /**
     * Return the number of picture of the ad
     *
     * @return int
     */
    public function getNbImage()
    {
        $node = $this->node->filter('.item_imageNumber');

        return $this->getFieldValue($node, 0, function ($value) {
            return (int)trim($value);
        });
    }

    /**
     * @return mixed
     */
    public function getPlacement()
    {
        $node = $this->node->filter('*[itemprop=availableAtOrFrom]');

        return $this->getFieldValue($node, '', function ($value) {
            return preg_replace('/\s+/', ' ', trim($value));
        });
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        $node = $this->node->filter('*[itemprop=category]');

        return $this->getFieldValue($node, false, function ($value) {
            if ('pro' === preg_replace('/[\s()]+/', '', $value)) {
                return 'pro';
            }

            return 'part';
        });
    }

    /**
     * @return object
     */
    public function getAll()
    {
        return (object)[
            'id'         => $this->getId(),
            'title'      => $this->getTitle(),
            'price'      => $this->getPrice(),
            'url'        => $this->getUrl(),
            'created_at' => $this->getCreatedAt(),
            'thumb'      => $this->getThumb(),
            'nb_image'   => $this->getNbImage(),
            'placement'  => $this->getPlacement(),
            'type'       => $this->getType(),
        ];
    }

    /**
     * Return the field's value
     *
     * @param Crawler $node
     * @param mixed $defaultValue
     * @param \Closure $callback
     * @param string $funcName
     * @param string $funcParam
     *
     * @return mixed
     */
    private function getFieldValue(
        Crawler $node,
        $defaultValue,
        $callback,
        $funcName = 'text',
        $funcParam = ''
    ) {
        if ($node->count()) {
            return $callback($node->$funcName($funcParam));
        }

        return $defaultValue;
    }
}
