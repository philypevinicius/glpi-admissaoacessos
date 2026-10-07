<?php

namespace GlpiPlugin\Admissaoacessos;

/**
 * Configuração geral (linha única, id=1): categorias ITIL do chamado de
 * admissão, modelo de tarefa base (AD), senha padrão (criptografada,
 * nunca em texto aberto) e o texto do e-mail de boas-vindas.
 */
class Config extends \CommonDBTM
{
    public static function getTable($classname = null)
    {
        return 'glpi_plugin_admissaoacessos_configs';
    }

    public static function getTypeName($nb = 0)
    {
        return __('Configuração', 'admissaoacessos');
    }

    public static function getConfig(): self
    {
        $config = new self();
        $config->getFromDB(1);
        return $config;
    }

    /** Nunca retorna a senha em texto aberto fora daqui - só no momento de montar o e-mail. */
    public function senhaPadraoDecriptada(): string
    {
        $cripto = (string) ($this->fields['senha_padrao_cripto'] ?? '');
        if ($cripto === '') {
            return '';
        }

        $glpikey = new \GLPIKey();
        $decriptada = $glpikey->decrypt($cripto);
        return $decriptada !== null ? $decriptada : '';
    }

    public function salvarSenhaPadrao(string $senhaTextoPuro): void
    {
        $glpikey = new \GLPIKey();
        $cripto = $senhaTextoPuro !== '' ? $glpikey->encrypt($senhaTextoPuro) : '';

        $this->update(['id' => 1, 'senha_padrao_cripto' => $cripto]);
    }

    /** @return int[] */
    public function categoriasIds(): array
    {
        global $DB;

        $ids = [];
        foreach ($DB->request(['FROM' => 'glpi_plugin_admissaoacessos_categorias']) as $linha) {
            $ids[] = (int) $linha['itilcategories_id'];
        }
        return $ids;
    }

    public function salvarCategorias(array $idsCategorias): void
    {
        global $DB;

        $DB->delete('glpi_plugin_admissaoacessos_categorias', [1 => 1]);
        foreach (array_unique(array_map('intval', $idsCategorias)) as $id) {
            if ($id > 0) {
                $DB->insert('glpi_plugin_admissaoacessos_categorias', ['itilcategories_id' => $id]);
            }
        }
    }
}
