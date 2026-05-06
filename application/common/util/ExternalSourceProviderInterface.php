<?php
namespace app\common\util;

interface ExternalSourceProviderInterface
{
    public function getCode();

    public function getLabel();

    /**
     * @param string $keyword
     * @param array $options
     * @return array
     */
    public function search($keyword, array $options = []);

    /**
     * @param array $options
     * @return array
     */
    public function fetchRecent(array $options = []);
}

