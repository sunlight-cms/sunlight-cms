<?php

namespace Sunlight;

/**
 * Trida pro dynamicke nacitani jazyk. balicku
 */
class LangPack implements \ArrayAccess
{
    /** @var string */
    protected $key;
    /** @var string */
    protected $dir;
    /** @var array|null */
    protected $list;
    /** @var bool */
    protected $loaded = false;

    /**
     * @param string     $key  pozadovany nazev klice v $_lang promenne
     * @param string     $dir  cesta k adresari s preklady (BEZ lomitka na konci)
     * @param array|null $list seznam dostupnych lokalizaci (zamezi nutne kontrole pres file_exists())
     */
    public function __construct($key, $dir, array $list = null)
    {
        $this->key = $key;
        $this->dir = $dir;
        $this->list = $list;
    }

    /**
     * Registrovat jazykovy balicek
     *
     * @param string     $key  pozadovany nazev klice v $_lang promenne
     * @param string     $dir  cesta k adresari s preklady vcetne lomitka na konci
     * @param array|null $list seznam dostupnych lokalizaci (zamezi nutne kontrole pres file_exists())
     * @return static
     */
    public static function register($key, $dir, array $list = null)
    {
        return $GLOBALS['_lang'][$key] = new static($key, $dir, $list);
    }

    /**
     * Zpracovat test existence klice
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        if (!$this->loaded) {
            $this->load();
        }

        return array_key_exists($offset, $GLOBALS['_lang'][$this->key]);
    }

    /**
     * Zpracovat ziskani klice
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if (!$this->loaded) {
            $this->load();
        }

        if (array_key_exists($offset, $GLOBALS['_lang'][$this->key])) {
            return $GLOBALS['_lang'][$this->key][$offset];
        } else {
            trigger_error(sprintf('Undefined index "%s"', $offset), E_USER_NOTICE);
        }
    }

    /**
     * Zpracovat nastaveni prvku
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        if (!$this->loaded) {
            $this->load();
        }

        $GLOBALS['_lang'][$this->key][$offset] = $value;
    }

    /**
     * Zpracovat smazani prvku
     *
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        if (!$this->loaded) {
            $this->load();
        }

        unset($GLOBALS['_lang'][$this->key][$offset]);
    }

    /**
     * Nacist jazykovy balicek
     */
    protected function load()
    {
        // sestavit cestu
        $path = $this->dir . '/' . _language . '.php';

        // pouzit fallback jazyk, pokud neni aktualni jazyk dostupny
        if (
            (null !== $this->list && !in_array(_language, $this->list) || null === $this->list && !is_file($path))
            && (Core::$fallbackLang === _language || !is_file($path = $this->dir . '/' . Core::$fallbackLang . '.php'))
        ) {
            $path = false;
        }

        // nacist balik
        $GLOBALS['_lang'][$this->key] = false !== $path ? (array) include $path : array();

        $this->loaded = true;
    }
}
