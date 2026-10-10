<?php

declare(strict_types=1);

namespace Laranex\PhpMyanmarPayments;

use Laranex\PhpMyanmarPayments\AyaPay\AyaPay;
use Laranex\PhpMyanmarPayments\AyaPay\AyaPayConfig;
use Laranex\PhpMyanmarPayments\CyberSource\CyberSource;
use Laranex\PhpMyanmarPayments\CyberSource\CyberSourceConfig;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPay;
use Laranex\PhpMyanmarPayments\KbzPay\KbzPayConfig;
use Laranex\PhpMyanmarPayments\WaveMoney\WaveMoney;
use Laranex\PhpMyanmarPayments\WaveMoney\WaveMoneyConfig;
use Laranex\PhpMyanmarPayments\YomaMmqr\YomaMmqr;
use Laranex\PhpMyanmarPayments\YomaMmqr\YomaMmqrConfig;
use Psr\Http\Client\ClientInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Builds each gateway from one configuration array. Gateways are created on first use, so only the
 * gateways you call need to be configured.
 */
class MyanmarPayments
{
    private ?KbzPay $kbzPay = null;

    private ?WaveMoney $waveMoney = null;

    private ?AyaPay $ayaPay = null;

    private ?YomaMmqr $yomaMmqr = null;

    private ?CyberSource $cyberSource = null;

    /**
     * @var array<string, callable(): object>
     */
    private array $envSources = [];

    /**
     * Each entry is a config object or the array its `fromArray()` takes.
     *
     * @param  array{kbz_pay?: KbzPayConfig|array<string, mixed>, wave_money?: WaveMoneyConfig|array<string, mixed>, aya_pay?: AyaPayConfig|array<string, mixed>, yoma_mmqr?: YomaMmqrConfig|array<string, mixed>, cyber_source?: CyberSourceConfig|array<string, mixed>}  $config
     */
    public function __construct(
        protected readonly array $config,
        protected readonly ?ClientInterface $httpClient = null,
        protected readonly ?CacheInterface $cache = null,
    ) {}

    /**
     * Read every gateway's configuration from environment variables (`KBZ_PAY_*`, `WAVE_MONEY_*`, `AYA_PAY_*`,
     * `YOMA_MMQR_*`, `CYBER_SOURCE_*` and `MYANMAR_PAYMENTS_HTTP_TIMEOUT`) when the gateway is first used.
     *
     * @param  array<array-key, mixed>|null  $env  Variables to read; defaults to `getenv()` merged with `$_ENV`.
     */
    public static function fromEnv(?array $env = null, ?ClientInterface $httpClient = null, ?CacheInterface $cache = null): self
    {
        $payments = new self([], $httpClient, $cache);
        $payments->envSources = [
            'kbz_pay' => fn (): KbzPayConfig => KbzPayConfig::fromEnv($env),
            'wave_money' => fn (): WaveMoneyConfig => WaveMoneyConfig::fromEnv($env),
            'aya_pay' => fn (): AyaPayConfig => AyaPayConfig::fromEnv($env),
            'yoma_mmqr' => fn (): YomaMmqrConfig => YomaMmqrConfig::fromEnv($env),
            'cyber_source' => fn (): CyberSourceConfig => CyberSourceConfig::fromEnv($env),
        ];

        return $payments;
    }

    public function kbzPay(): KbzPay
    {
        if ($this->kbzPay === null) {
            $entry = $this->entry('kbz_pay');
            $this->kbzPay = $this->newKbzPay($entry instanceof KbzPayConfig ? $entry : KbzPayConfig::fromArray($this->configFor('kbz_pay')));
        }

        return $this->kbzPay;
    }

    public function waveMoney(): WaveMoney
    {
        if ($this->waveMoney === null) {
            $entry = $this->entry('wave_money');
            $this->waveMoney = $this->newWaveMoney($entry instanceof WaveMoneyConfig ? $entry : WaveMoneyConfig::fromArray($this->configFor('wave_money')));
        }

        return $this->waveMoney;
    }

    public function ayaPay(): AyaPay
    {
        if ($this->ayaPay === null) {
            $entry = $this->entry('aya_pay');
            $this->ayaPay = $this->newAyaPay($entry instanceof AyaPayConfig ? $entry : AyaPayConfig::fromArray($this->configFor('aya_pay')));
        }

        return $this->ayaPay;
    }

    public function yomaMmqr(): YomaMmqr
    {
        if ($this->yomaMmqr === null) {
            $entry = $this->entry('yoma_mmqr');
            $this->yomaMmqr = $this->newYomaMmqr($entry instanceof YomaMmqrConfig ? $entry : YomaMmqrConfig::fromArray($this->configFor('yoma_mmqr')));
        }

        return $this->yomaMmqr;
    }

    public function cyberSource(): CyberSource
    {
        if ($this->cyberSource === null) {
            $entry = $this->entry('cyber_source');
            $this->cyberSource = $this->newCyberSource($entry instanceof CyberSourceConfig ? $entry : CyberSourceConfig::fromArray($this->configFor('cyber_source')));
        }

        return $this->cyberSource;
    }

    protected function newKbzPay(KbzPayConfig $config): KbzPay
    {
        return new KbzPay($config, $this->httpClient);
    }

    protected function newWaveMoney(WaveMoneyConfig $config): WaveMoney
    {
        return new WaveMoney($config, $this->httpClient);
    }

    protected function newAyaPay(AyaPayConfig $config): AyaPay
    {
        return new AyaPay($config, $this->httpClient);
    }

    protected function newYomaMmqr(YomaMmqrConfig $config): YomaMmqr
    {
        return new YomaMmqr($config, $this->httpClient, $this->cache);
    }

    protected function newCyberSource(CyberSourceConfig $config): CyberSource
    {
        return new CyberSource($config);
    }

    /**
     * The gateway's entry as an array, or `[]` when it is a config object or missing.
     *
     * @return array<string, mixed>
     */
    protected function configFor(string $gateway): array
    {
        $entry = $this->config[$gateway] ?? [];

        return is_array($entry) ? $entry : [];
    }

    /**
     * The gateway's configured entry: a config object, an array, or null.
     */
    private function entry(string $gateway): mixed
    {
        $source = $this->envSources[$gateway] ?? null;

        return $source !== null ? $source() : ($this->config[$gateway] ?? null);
    }
}
