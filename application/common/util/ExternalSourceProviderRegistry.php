<?php
namespace app\common\util;

class ExternalSourceProviderRegistry
{
    public function createByCode($code, array $providerConfig = [])
    {
        $code = strtolower(trim((string)$code));
        if ($code === 'tmdb') {
            return new TmdbExternalSourceProvider($providerConfig);
        }
        if ($code === 'douban') {
            return new DoubanExternalSourceProvider($providerConfig);
        }
        if ($code === 'imdb') {
            return new ImdbExternalSourceProvider($providerConfig);
        }
        return null;
    }

    public function listEnabledProviders(array $extCfg)
    {
        $out = [];
        $sources = isset($extCfg['sources']) && is_array($extCfg['sources']) ? $extCfg['sources'] : [];
        foreach ($sources as $code => $cfg) {
            if (!is_array($cfg)) {
                continue;
            }
            if ((string)(isset($cfg['enabled']) ? $cfg['enabled'] : '0') !== '1') {
                continue;
            }
            $provider = $this->createByCode($code, $cfg);
            if ($provider !== null) {
                $out[$code] = $provider;
            }
        }
        return $out;
    }
}

