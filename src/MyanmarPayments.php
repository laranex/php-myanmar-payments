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
     * @param  array{kbz_pay?: array<string, mixed>, wave_money?: array<string, mixed>, aya_pay?: array<string, mixed>, yoma_mmqr?: array<string, mixed>, cyber_source?: array<string, mixed>}  $config
     */
    public function __construct(
        protected readonly array $config,
        protected readonly ?ClientInterface $httpClient = null,
        protected readonly ?CacheInterface $cache = null,
    ) {}

    public function kbzPay(): KbzPay
    {
        return $this->kbzPay ??= $this->newKbzPay(KbzPayConfig::fromArray($this->configFor('kbz_pay')));
    }

    public function waveMoney(): WaveMoney
    {
        return $this->waveMoney ??= $this->newWaveMoney(WaveMoneyConfig::fromArray($this->configFor('wave_money')));
    }

    public function ayaPay(): AyaPay
    {
        return $this->ayaPay ??= $this->newAyaPay(AyaPayConfig::fromArray($this->configFor('aya_pay')));
    }

    public function yomaMmqr(): YomaMmqr
    {
        return $this->yomaMmqr ??= $this->newYomaMmqr(YomaMmqrConfig::fromArray($this->configFor('yoma_mmqr')));
    }

    public function cyberSource(): CyberSource
    {
        return $this->cyberSource ??= $this->newCyberSource(CyberSourceConfig::fromArray($this->configFor('cyber_source')));
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
     * @return array<string, mixed>
     */
    protected function configFor(string $gateway): array
    {
        return (array) ($this->config[$gateway] ?? []);
    }
}
