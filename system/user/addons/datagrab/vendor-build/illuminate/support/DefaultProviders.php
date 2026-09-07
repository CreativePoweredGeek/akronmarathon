<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Support;

class DefaultProviders
{
    /**
     * The current providers.
     *
     * @var array
     */
    protected $providers;
    /**
     * Create a new default provider collection.
     */
    public function __construct(?array $providers = null)
    {
        $this->providers = $providers ?: [\BoldMinded\DataGrab\Dependency\Illuminate\Auth\AuthServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Broadcasting\BroadcastServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Bus\BusServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Cache\CacheServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Concurrency\ConcurrencyServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Cookie\CookieServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Database\DatabaseServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Encryption\EncryptionServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Filesystem\FilesystemServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Foundation\Providers\FoundationServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Hashing\HashServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Mail\MailServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Notifications\NotificationServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Pagination\PaginationServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Auth\Passwords\PasswordResetServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Pipeline\PipelineServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Queue\QueueServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Redis\RedisServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Session\SessionServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Translation\TranslationServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\Validation\ValidationServiceProvider::class, \BoldMinded\DataGrab\Dependency\Illuminate\View\ViewServiceProvider::class];
    }
    /**
     * Merge the given providers into the provider collection.
     *
     * @param  array  $providers
     * @return static
     */
    public function merge(array $providers)
    {
        $this->providers = \array_merge($this->providers, $providers);
        return new static($this->providers);
    }
    /**
     * Replace the given providers with other providers.
     *
     * @param  array  $replacements
     * @return static
     */
    public function replace(array $replacements)
    {
        $current = new Collection($this->providers);
        foreach ($replacements as $from => $to) {
            $key = $current->search($from);
            $current = \is_int($key) ? $current->replace([$key => $to]) : $current;
        }
        return new static($current->values()->toArray());
    }
    /**
     * Disable the given providers.
     *
     * @param  array  $providers
     * @return static
     */
    public function except(array $providers)
    {
        return new static((new Collection($this->providers))->reject(fn($p) => \in_array($p, $providers))->values()->toArray());
    }
    /**
     * Convert the provider collection to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->providers;
    }
}
