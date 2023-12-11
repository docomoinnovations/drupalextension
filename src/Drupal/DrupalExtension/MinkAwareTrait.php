<?php

namespace Drupal\DrupalExtension;

use Behat\Mink\Mink;
use Behat\Mink\Element\Element;

/**
 * Provides helpful methods to interact with the Mink session.
 *
 * This is making the functionality from RawMinkContext available for reuse in
 * classes that do not extend it.
 *
 * @see \Behat\MinkExtension\Context\RawMinkContext
 */
trait MinkAwareTrait
{

    /**
     * The Mink sessions manager.
     *
     * @var \Behat\Mink\Mink
     */
    protected $mink;

    /**
     * The parameters for the Mink extension.
     *
     * @var array
     */
    protected $minkParameters;

    /**
     * Sets the Mink sessions manager.
     *
     * @param \Behat\Mink\Mink $mink
     *   The Mink sessions manager.
     */
    public function setMink(Mink $mink)
    {
        $this->mink = $mink;
    }

    /**
     * Returns the Mink sessions manager.
     *
     * @return \Behat\Mink\Mink
     *   The Mink sessions manager.
     */
    public function getMink()
    {
        return $this->mink;
    }

    /**
     * Returns the Mink session.
     *
     * @param string|null $name
     *   The name of the session to return. If omitted the active session will
     *   be returned.
     *
     * @return \Behat\Mink\Session
     *   The Mink session.
     */
    public function getSession($name = null)
    {
        return $this->getMink()->getSession($name);
    }

    /**
     * Returns the parameters provided for Mink.
     *
     * @return array
     */
    public function getMinkParameters()
    {
        return $this->minkParameters;
    }

    /**
     * Sets parameters provided for Mink.
     *
     * @param array $parameters
     */
    public function setMinkParameters(array $parameters)
    {
        $this->minkParameters = $parameters;
    }

    /**
     * Returns a specific mink parameter.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getMinkParameter($name)
    {
        return isset($this->minkParameters[$name]) ? $this->minkParameters[$name] : null;
    }

    /**
     * Applies the given parameter to the Mink configuration.
     *
     * Consider that this will only be applied in scope of the class that is
     * using this trait.
     *
     * @param string $name  The key of the parameter
     * @param string $value The value of the parameter
     */
    public function setMinkParameter($name, $value)
    {
        $this->minkParameters[$name] = $value;
    }

    /**
     * Returns Mink session assertion tool.
     *
     * @param string|null $name
     *   The name of the session to return. If omitted the active session will
     *   be returned.
     *
     * @return \Behat\Mink\WebAssert
     */
    public function assertSession($name = null)
    {
        return $this->getMink()->assertSession($name);
    }

    /**
     * Visits provided relative path using provided or default session.
     *
     * @param string $path
     * @param string|null $sessionName
     */
    public function visitPath($path, $sessionName = null)
    {
        $this->getSession($sessionName)->visit($this->locatePath($path));
    }

    /**
     * Locates url, based on provided path.
     * Override to provide custom routing mechanism.
     *
     * @param string $path
     *
     * @return string
     */
    public function locatePath($path)
    {
        $startUrl = rtrim($this->getMinkParameter('base_url'), '/') . '/';

        return 0 !== strpos($path, 'http') ? $startUrl . ltrim($path, '/') : $path;
    }

    /**
     * Save a screenshot of the current window to the file system.
     *
     * @param string $filename
     *   Desired filename, defaults to <browser>_<ISO 8601 date>_<randomId>.png.
     * @param string $filepath
     *   Desired filepath, defaults to upload_tmp_dir, falls back to
     *   sys_get_temp_dir().
     */
    public function saveScreenshot($filename = null, $filepath = null)
    {
        // Under Cygwin, uniqid with more_entropy must be set to true.
        // No effect in other environments.
        $filename = $filename ?: sprintf('%s_%s_%s.%s', $this->getMinkParameter('browser_name'), date('c'), uniqid('', true), 'png');
        $filepath = $filepath ? $filepath : (ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir());
        file_put_contents($filepath . '/' . $filename, $this->getSession()->getScreenshot());
    }

    /**
     * Return a region from the current page.
     *
     * @param string $region
     *   The machine name of the region to return.
     *
     * @return \Behat\Mink\Element\NodeElement
     *
     * @throws \Exception
     *   If the region cannot be found.
     */
    public function getRegion($region): \Behat\Mink\Element\NodeElement {
        $session = $this->getSession();
        $regionObj = $session->getPage()->find('region', $region);
        if (!$regionObj) {
            throw new \Exception(sprintf('No region "%s" found on the page %s.', $region, $session->getCurrentUrl()));
        }
        return $regionObj;
    }

    /**
     * See if the table rows contains specified text from a given element.
     *
     * @param Element $element
     *   \Behat\Mink\Element\Element object.
     * @param string $search
     *   The text to search for in the table row.
     *
     * @return bool
     *
     */
    public function hasTableRows(Element $element, string $search): bool {
        $rows = $element->findAll('css', 'tr');
        if (empty($rows)) {
            return FALSE;
        }
        array_filter($rows, static function ($row) use ($search) {
            if (strpos($row->getText(), $search) !== FALSE) {
                return TRUE;
            }
        });
        return FALSE;
    }

    /**
     * Retrieve table rows containing specified text from a given element.
     *
     * @param Element $element
     *   \Behat\Mink\Element\Element object.
     * @param string $search
     *   The text to search for in the table row.
     *
     * @return array of \Behat\Mink\Element\NodeElement
     *
     * @throws \RuntimeException
     */
    public function getTableRows(Element $element, string $search): array {
        $rows = $element->findAll('css', 'tr');
        if (empty($rows)) {
            throw new \RuntimeException(sprintf('No rows found on the page %s', $this->getSession()->getCurrentUrl()));
        }
        $rows = array_filter($rows, static function ($row) use ($search) {
            if (strpos($row->getText(), $search) !== FALSE) {
                return $row;
            }
        });
        if (empty($rows)) {
            throw new \RuntimeException(sprintf('Failed to find a row containing "%s" on the page %s', $search, $this->getSession()->getCurrentUrl()));
        }
        return $rows;
    }

    /**
     * Retrieve a table row containing specified text from a given element.
     *
     * @param \Behat\Mink\Element\Element
     * @param string
     *   The text to search for in the table row.
     *
     * @return \Behat\Mink\Element\NodeElement
     *
     * @throws \Exception
     */
    public function getTableRow(Element $element, string $search): \Behat\Mink\Element\NodeElement {
        $rows = $element->findAll('css', 'tr');
        if (empty($rows)) {
            throw new \Exception(sprintf('No rows found on the page %s', $this->getSession()->getCurrentUrl()));
        }
        foreach ($rows as $row) {
            if (strpos($row->getText(), $search) !== false) {
                return $row;
            }
        }
        throw new \Exception(sprintf('Failed to find a row containing "%s" on the page %s', $search, $this->getSession()->getCurrentUrl()));
    }

    /**
     * See if the element has a table.
     *
     * @param Element $element
     *    \Behat\Mink\Element\Element object.
     *
     * @return bool
     *   True if the element has a table.
     */
    private function hasTable(Element $element): bool {
        $rows = $element->findAll('css', 'tr');
        return !empty($rows);
    }

}
