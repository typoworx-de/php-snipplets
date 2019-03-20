<?php
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class AuthorativeDnsQuery
{
    protected static $cacheBackend = 'file';
    protected static $cacheTTL = 60 * 60;   // seconds, 1h

    protected static $rootServers = [
        'a.root-servers.net',
        'b.root-servers.net',
        'c.root-servers.net',
        'd.root-servers.net',
        'e.root-servers.net',
        'f.root-servers.net',
        'g.root-servers.net',
        'h.root-servers.net',
        'i.root-servers.net',
        'j.root-servers.net',
        'k.root-servers.net',
        'l.root-servers.net',
        'm.root-servers.net',
    ];

    /**
     * @param string $host
     * @param bool $getAll
     * @return mixed|null
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected static function getAuthorativeServer(string $host, $getAll = false)
    {
        $tld = substr($host, strrpos($host, '.') + 1);

        $cacheKey = sprintf('%s::%s', __METHOD__, $tld);
        if(Cache::store(self::$cacheBackend)->has($cacheKey))
        {
            $records = Cache::store(self::$cacheBackend)->get($cacheKey);
        }
        else
        {
            $index = mt_rand(0, count(self::$rootServers)-1);

            $process = new Process(['dig', $host, 'NS', '@'.self::$rootServers[ $index ], '+nocmd', '+answer', '+multiline']);
            $process->run();

            $output = explode("\n", $process->getOutput());

            $output = array_filter($output, function($line) {
                $line = trim($line, " ");

                if(empty($line))
                {
                    return false;
                }

                return !(strpos($line, ';') === 0 && strpos($line, ';;') !== 0);
            });

            $startParse = false;

            $records = [];
            reset($output);
            do
            {
                $line = current($output);

                if(stripos($line, ';; ADDITIONAL SECTION') === 0)
                {
                    $startParse = true;
                    continue;
                }
                else if(strpos($line, ";;") === 0)
                {
                    $startParse = false;
                }

                if($startParse === true && preg_match('/^([^\t ]+)/', $line, $entry))
                {
                    $records[] = rtrim(array_pop($entry), '. ');
                }
            }
            while(next($output));

            if(count($records))
            {
                Cache::store(self::$cacheBackend)->set($cacheKey, $records, self::$cacheTTL);
            }
            else
            {
                Cache::store(self::$cacheBackend)->set($cacheKey, null, self::$cacheTTL);
            }
        }

        if (count($records))
        {
            if($getAll === true)
            {
                return $records;
            }
            else
            {
                $index = mt_rand(0, count($records) - 1);

                return $records[$index];
            }
        }

        return null;
    }

    public static function flushAll()
    {
        Cache::store(self::$cacheBackend)->clear();
    }
}
