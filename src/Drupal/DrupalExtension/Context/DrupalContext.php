<?php

namespace Drupal\DrupalExtension\Context;

use Behat\Behat\Context\TranslatableContext;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\Element;

use Drupal\DrupalExtension\FeatureTrait;
use Drupal\DrupalExtension\MinkAwareTrait;

/**
 * Provides pre-built step definitions for interacting with Drupal.
 */
class DrupalContext extends RawDrupalContext implements TranslatableContext
{

  use FeatureTrait, MinkAwareTrait;

  /**
   * Returns list of definition translation resources paths.
   *
   * @return array
   */
    public static function getTranslationResources()
    {
        return glob(__DIR__ . '/../../../../i18n/*.xliff');
    }

  /**
   * @Given I am an anonymous user
   * @Given I am not logged in
   * @Then I log out
   */
    public function assertAnonymousUser()
    {
        // Verify the user is logged out.
        $this->logout(true);
    }

  /**
   * Creates and authenticates a user with the given role(s).
   *
   * @Given I am logged in as a user with the :role role(s)
   * @Given I am logged in as a/an :role
   */
    public function assertAuthenticatedByRole($role)
    {
        // Check if a user with this role is already logged in.
        if (!$this->loggedInWithRole($role)) {
            // Create user (and project)
            $user = (object) [
                'name' => $this->getRandom()->name(8),
                'pass' => $this->getRandom()->name(16),
                'role' => $role,
            ];
            $user->mail = "{$user->name}@example.com";

            $this->userCreate($user);

            $roles = explode(',', $role);
            $roles = array_map('trim', $roles);
            foreach ($roles as $role) {
                if (!in_array(strtolower($role), ['authenticated', 'authenticated user'])) {
                    // Only add roles other than 'authenticated user'.
                    $this->getDriver()->userAddRole($user, $role);
                }
            }

            // Login.
            $this->login($user);
        }
    }

  /**
   * Creates and authenticates a user with the given role(s) and given fields.
   * | field_user_name     | John  |
   * | field_user_surname  | Smith |
   * | ...                 | ...   |
   *
   * @Given I am logged in as a user with the :role role(s) and I have the following fields:
   */
    public function assertAuthenticatedByRoleWithGivenFields($role, TableNode $fields)
    {
        // Check if a user with this role is already logged in.
        if (!$this->loggedInWithRole($role)) {
            // Create user (and project)
            $user = (object) [
                'name' => $this->getRandom()->name(8),
                'pass' => $this->getRandom()->name(16),
                'role' => $role,
            ];
            $user->mail = "{$user->name}@example.com";

            // Assign fields to user before creation.
            foreach ($fields->getRowsHash() as $field => $value) {
                  $user->{$field} = $value;
            }

            $this->userCreate($user);

            $roles = explode(',', $role);
            $roles = array_map('trim', $roles);
            foreach ($roles as $role) {
                if (!in_array(strtolower($role), ['authenticated', 'authenticated user'])) {
                    // Only add roles other than 'authenticated user'.
                    $this->getDriver()->userAddRole($user, $role);
                }
            }

            // Login.
            $this->login($user);
        }
    }


  /**
   * @Given I am logged in as :name
   */
    public function assertLoggedInByName($name)
    {
        $manager = $this->getUserManager();

        // Change internal current user.
        $manager->setCurrentUser($manager->getUser($name));

        // Login.
        $this->login($manager->getUser($name));
    }

  /**
   * @Given I am logged in as a user with the :permissions permission(s)
   */
    public function assertLoggedInWithPermissions($permissions)
    {
        // Create a temporary role with given permissions.
        $permissions = array_map('trim', explode(',', $permissions));
        $role = $this->getDriver()->roleCreate($permissions);

        // Create user.
        $user = (object) [
            'name' => $this->getRandom()->name(8),
            'pass' => $this->getRandom()->name(16),
            'role' => $role,
        ];
        $user->mail = "{$user->name}@example.com";
        $this->userCreate($user);

        // Assign the temporary role with given permissions.
        $this->getDriver()->userAddRole($user, $role);
        $this->roles[] = $role;

        // Login.
        $this->login($user);
    }

  /**
   * Find text in a table row containing given text.
   *
   * @Then I should see (the text ):text in the :rowText row
   */
    public function assertTextInTableRow($text, $rowText): void {
      $rows = $this->getTableRows($this->getSession()->getPage(), $rowText);
      foreach ($rows ?: [] as $row) {
        if (strpos($row->getText(), $text) !== FALSE) {
          return;
        }
      }
      throw new \RuntimeException(sprintf('Found a row containing "%s", but it did not contain the text "%s".', $rowText, $text));
    }

  /**
   * Find text in all table rows containing given text.
   *
   * @Then I should see (the text ):text in all the :rowText rows
   */
  public function assertTextInTableRows($text, $rowText): void {
    if (!$this->hasTableRows($this->getSession()->getPage(), $rowText)) {
      throw new \RuntimeException(sprintf('Found a row containing "%s", but it did not contain the text "%s".', $rowText, $text));
    }
  }

  /**
   * Assert text not in a table row containing given text.
   * If multiple rows are found, check the first one only.
   *
   * @Then I should not see (the text ):text in the :rowText row
   */
  public function assertTextNotInTableRow($text, $rowText): void {
    $row = $this->getTableRow($this->getSession()->getPage(), $rowText);
    if (strpos($row->getText(), $text) !== FALSE) {
      throw new \Exception(sprintf('Found a row containing "%s", but it contained the text "%s".', $rowText, $text));
    }
  }

  /**
   * Assert text not in any table rows containing given text.
   *
   * @Then I should not see (the text ):text in any :rowText rows
   */
    public function assertTextNotInAnyTableRows($text, $rowText): void {
      $page = $this->getSession()->getPage();
      if (!$this->hasTableRows($page, $rowText)) {
        return;
      }
      $rows = $this->getTableRows($page, $rowText);
      foreach ($rows ?: [] as $row) {
        if (strpos($row->getText(), $text) !== FALSE) {
          throw new \RuntimeException(sprintf('Found a row containing "%s", but it contained the text "%s".', $rowText, $text));
        }
      }
    }

  /**
   * Assert text not in any table rows containing given text, or no table.
   *
   * @Then I should not see (the text ):text in any :rowText rows or no table
   */
  public function assertTextNotInTableRowOrNoTable($text, $rowText): void {
    if ($this->hasTable($this->getSession()->getPage())) {
      $this->assertTextNotInAnyTableRows($text, $rowText);
    }
  }

  /**
   * Find text in a table row containing given text, no text in a table row, or
   * no table. This is used for the transient status.
   *
   * @Then I should see with (the text ):text in the :rowText row, no rows, or no table
   */
  public function assertNoTableRowOrSeeWithText($text, $rowText): void {
    $page = $this->getSession()->getPage();
    if (!$this->hasTable($page) || !$this->hasTableRows($page, $rowText)) {
      return;
    }
    $rows = $this->getTableRows($page, $rowText);
    foreach ($rows ?: [] as $row) {
      if (strpos($row->getText(), $text) === FALSE) {
        throw new \RuntimeException(sprintf('Found a row containing "%s", but it did not contain the text "%s".', $rowText, $text));
      }
    }
  }

  /**
   * Attempts to find a link in a table row containing giving text. This is for
   * administrative pages such as the administer content types screen found at
   * `admin/structure/types`.
   *
   * @Given I click :link in the :rowText row
   * @Then I (should )see the :link in the :rowText row
   */
    public function assertClickInTableRow($link, $rowText)
    {
        $page = $this->getSession()->getPage();
        if ($link_element = $this->getTableRow($page, $rowText)->findLink($link)) {
            // Click the link and return.
            $link_element->click();
            return;
        }
        throw new \Exception(sprintf('Found a row containing "%s", but no "%s" link on the page %s', $rowText, $link, $this->getSession()->getCurrentUrl()));
    }

  /**
   * @Given the cache has been cleared
   */
    public function assertCacheClear()
    {
        $this->getDriver()->clearCache();
    }

  /**
   * @Given I run cron
   */
    public function assertCron()
    {
        $this->getDriver()->runCron();
    }

  /**
   * Creates content of the given type.
   *
   * @Given I am viewing a/an :type (content )with the title :title
   * @Given a/an :type (content )with the title :title
   */
    public function createNode($type, $title)
    {
        // @todo make this easily extensible.
        $node = (object) [
            'title' => $title,
            'type' => $type,
        ];
        $saved = $this->nodeCreate($node);
        // Set internal page on the new node.
        $this->getSession()->visit($this->locatePath('/node/' . $saved->nid));
    }

  /**
   * Creates content authored by the current user.
   *
   * @Given I am viewing my :type (content )with the title :title
   */
    public function createMyNode($type, $title)
    {
        if ($this->getUserManager()->currentUserIsAnonymous()) {
            throw new \Exception(sprintf('There is no current logged in user to create a node for.'));
        }

        $node = (object) [
            'title' => $title,
            'type' => $type,
            'body' => $this->getRandom()->name(255),
            'uid' => $this->getUserManager()->getCurrentUser()->uid,
        ];
        $saved = $this->nodeCreate($node);

        // Set internal page on the new node.
        $this->getSession()->visit($this->locatePath('/node/' . $saved->nid));
    }

  /**
   * Creates content of a given type provided in the form:
   * | title    | author     | status | created           |
   * | My title | Joe Editor | 1      | 2014-10-17 8:00am |
   * | ...      | ...        | ...    | ...               |
   *
   * @Given :type content:
   */
    public function createNodes($type, TableNode $nodesTable)
    {
        foreach ($nodesTable->getHash() as $nodeHash) {
            $node = (object) $nodeHash;
            $node->type = $type;
            $this->nodeCreate($node);
        }
    }

  /**
   * Creates content of the given type, provided in the form:
   * | title     | My node        |
   * | Field One | My field value |
   * | author    | Joe Editor     |
   * | status    | 1              |
   * | ...       | ...            |
   *
   * @Given I am viewing a/an :type( content):
   */
    public function assertViewingNode($type, TableNode $fields)
    {
        $node = (object) [
            'type' => $type,
        ];
        foreach ($fields->getRowsHash() as $field => $value) {
            $node->{$field} = $value;
        }

        $saved = $this->nodeCreate($node);

        // Set internal browser on the node.
        $this->getSession()->visit($this->locatePath('/node/' . $saved->nid));
    }

  /**
   * Asserts that a given content type is editable.
   *
   * @Then I should be able to edit a/an :type( content)
   */
    public function assertEditNodeOfType($type)
    {
        $node = (object) [
            'type' => $type,
            'title' => "Test $type",
        ];
        $saved = $this->nodeCreate($node);

        // Set internal browser on the node edit page.
        $this->getSession()->visit($this->locatePath('/node/' . $saved->nid . '/edit'));

        // Test status.
        $this->assertSession()->statusCodeEquals('200');
    }


  /**
   * Creates a term on an existing vocabulary.
   *
   * @Given I am viewing a/an :vocabulary term with the name :name
   * @Given a/an :vocabulary term with the name :name
   */
    public function createTerm($vocabulary, $name)
    {
        // @todo make this easily extensible.
        $term = (object) [
            'name' => $name,
            'vocabulary_machine_name' => $vocabulary,
            'description' => $this->getRandom()->name(255),
        ];
        $saved = $this->termCreate($term);

        // Set internal page on the term.
        $this->getSession()->visit($this->locatePath('/taxonomy/term/' . $saved->tid));
    }

  /**
   * Creates multiple users.
   *
   * Provide user data in the following format:
   *
   * | name     | mail         | roles        |
   * | user foo | foo@bar.com  | role1, role2 |
   *
   * @Given users:
   */
    public function createUsers(TableNode $usersTable)
    {
        foreach ($usersTable->getHash() as $userHash) {
            // Split out roles to process after user is created.
            $roles = [];
            if (isset($userHash['roles'])) {
                $roles = explode(',', $userHash['roles']);
                $roles = array_filter(array_map('trim', $roles));
                unset($userHash['roles']);
            }

            $user = (object) $userHash;
            // Set a password.
            if (!isset($user->pass)) {
                $user->pass = $this->getRandom()->name();
            }
            $this->userCreate($user);

            // Assign roles.
            foreach ($roles as $role) {
                $this->getDriver()->userAddRole($user, $role);
            }
        }
    }

  /**
   * Creates one or more terms on an existing vocabulary.
   *
   * Provide term data in the following format:
   *
   * | name  | parent | description | weight | taxonomy_field_image |
   * | Snook | Fish   | Marine fish | 10     | snook-123.jpg        |
   * | ...   | ...    | ...         | ...    | ...                  |
   *
   * Only the 'name' field is required.
   *
   * @Given :vocabulary terms:
   */
    public function createTerms($vocabulary, TableNode $termsTable)
    {
        foreach ($termsTable->getHash() as $termsHash) {
            $term = (object) $termsHash;
            $term->vocabulary_machine_name = $vocabulary;
            $this->termCreate($term);
        }
    }

  /**
   * Creates one or more languages.
   *
   * @Given the/these (following )languages are available:
   *
   * Provide language data in the following format:
   *
   * | langcode |
   * | en       |
   * | fr       |
   *
   * @param TableNode $langcodesTable
   *   The table listing languages by their ISO code.
   */
    public function createLanguages(TableNode $langcodesTable)
    {
        foreach ($langcodesTable->getHash() as $row) {
            $language = (object) [
                'langcode' => $row['languages'],
            ];
            $this->languageCreate($language);
        }
    }

  /**
   * Pauses the scenario until the user presses a key. Useful when debugging a scenario.
   *
   * @Then (I )break
   */
    public function iPutABreakpoint()
    {
        fwrite(STDOUT, "\033[s \033[93m[Breakpoint] Press \033[1;93m[RETURN]\033[0;93m to continue, or 'q' to quit...\033[0m");
        do {
            $line = trim(fgets(STDIN, 1024));
            //Note: this assumes ASCII encoding.  Should probably be revamped to
            //handle other character sets.
            $charCode = ord($line);
            switch ($charCode) {
                case 0: //CR
                case 121: //y
                case 89: //Y
                    break 2;
                // case 78: //N
                // case 110: //n
                case 113: //q
                case 81: //Q
                    throw new \Exception("Exiting test intentionally.");
                default:
                    fwrite(STDOUT, sprintf("\nInvalid entry '%s'.  Please enter 'y', 'q', or the enter key.\n", $line));
                    break;
            }
        } while (true);
        fwrite(STDOUT, "\033[u");
    }

}