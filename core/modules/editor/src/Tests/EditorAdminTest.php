<?php

/**
 * @file
 * Contains \Drupal\editor\Tests\EditorAdminTest.
 */

namespace Drupal\editor\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests administration of text editors.
 *
 * @group editor
 */
class EditorAdminTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('filter', 'editor');

  public function setUp() {
    parent::setUp();

    // Add text format.
    $filtered_html_format = entity_create('filter_format', array(
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
      'weight' => 0,
      'filters' => array(),
    ));
    $filtered_html_format->save();

    // Create admin user.
    $this->admin_user = $this->drupalCreateUser(array('administer filters'));
  }

  /**
   * Tests an existing format without any editors available.
   */
  public function testNoEditorAvailable() {
    $this->drupalLogin($this->admin_user);
    $this->drupalGet('admin/config/content/formats/manage/filtered_html');

    // Ensure the form field order is correct.
    $roles_pos = strpos($this->drupalGetContent(), 'Roles');
    $editor_pos = strpos($this->drupalGetContent(), 'Text editor');
    $filters_pos = strpos($this->drupalGetContent(), 'Enabled filters');
    $this->assertTrue($roles_pos < $editor_pos && $editor_pos < $filters_pos, '"Text Editor" select appears in the correct location of the text format configuration UI.');

    // Verify the <select>.
    $select = $this->xpath('//select[@name="editor[editor]"]');
    $select_is_disabled = $this->xpath('//select[@name="editor[editor]" and @disabled="disabled"]');
    $options = $this->xpath('//select[@name="editor[editor]"]/option');
    $this->assertTrue(count($select) === 1, 'The Text Editor select exists.');
    $this->assertTrue(count($select_is_disabled) === 1, 'The Text Editor select is disabled.');
    $this->assertTrue(count($options) === 1, 'The Text Editor select has only one option.');
    $this->assertTrue(((string) $options[0]) === 'None', 'Option 1 in the Text Editor select is "None".');
    $this->assertRaw(t('This option is disabled because no modules that provide a text editor are currently enabled.'), 'Description for select present that tells users to install a text editor module.');
  }

  /**
   * Tests adding a text editor to an existing text format.
   */
  public function testAddEditorToExistingFormat() {
    $this->enableUnicornEditor();
    $this->drupalLogin($this->admin_user);
    $this->drupalGet('admin/config/content/formats/manage/filtered_html');
    $edit = $this->selectUnicornEditor();
    // Configure Unicorn Editor's setting to another value.
    $edit['editor[settings][foo]'] = 'baz';
    $this->drupalPostForm(NULL, $edit, t('Save configuration'));
    $this->verifyUnicornEditorConfiguration('filtered_html', 'baz');
  }

  /**
   * Tests adding a text editor to a new text format.
   */
  public function testAddEditorToNewFormat() {
    $this->enableUnicornEditor();
    $this->drupalLogin($this->admin_user);
    $this->drupalGet('admin/config/content/formats/add');
    // Configure the text format name.
    $edit = array(
      'name' => 'Monocerus',
      'format' => 'monocerus',
    );
    $edit += $this->selectUnicornEditor();
    $this->drupalPostForm(NULL, $edit, t('Save configuration'));
    $this->verifyUnicornEditorConfiguration($edit['format']);
  }

  /**
   * Enables the unicorn editor.
   */
  protected function enableUnicornEditor() {
    \Drupal::moduleHandler()->install(array('editor_test'));
    $this->rebuildContainer();
    $this->resetAll();
  }

  /**
   * Tests and selects the unicorn editor.
   *
   * @return array
   *   Returns an edit array containing the values to be posted.
   */
  protected function selectUnicornEditor() {
    // Verify the <select> when a text editor is available.
    $select = $this->xpath('//select[@name="editor[editor]"]');
    $select_is_disabled = $this->xpath('//select[@name="editor[editor]" and @disabled="disabled"]');
    $options = $this->xpath('//select[@name="editor[editor]"]/option');
    $this->assertTrue(count($select) === 1, 'The Text Editor select exists.');
    $this->assertTrue(count($select_is_disabled) === 0, 'The Text Editor select is not disabled.');
    $this->assertTrue(count($options) === 2, 'The Text Editor select has two options.');
    $this->assertTrue(((string) $options[0]) === 'None', 'Option 1 in the Text Editor select is "None".');
    $this->assertTrue(((string) $options[1]) === 'Unicorn Editor', 'Option 2 in the Text Editor select is "Unicorn Editor".');
    $this->assertTrue(((string) $options[0]['selected']) === 'selected', 'Option 1 ("None") is selected.');
    // Ensure the none option is selected
    $this->assertNoRaw(t('This option is disabled because no modules that provide a text editor are currently enabled.'), 'Description for select absent that tells users to install a text editor module.');

    // Select the "Unicorn Editor" editor and click the "Configure" button.
    $edit = array(
      'editor[editor]' => 'unicorn',
    );
    $this->drupalPostAjaxForm(NULL, $edit, 'editor_configure');
    $unicorn_setting_foo = $this->xpath('//input[@name="editor[settings][foo]" and @type="text" and @value="bar"]');
    $this->assertTrue(count($unicorn_setting_foo), "Unicorn Editor's settings form is present.");

    return $edit;
  }

  /**
   * Verifies unicorn editor configuration.
   *
   * @param string $format_id
   *   The format machine name.
   * @param string $foo
   *   The expected value of the foo setting.
   */
  protected function verifyUnicornEditorConfiguration($format_id, $foo = 'bar') {
    $editor = editor_load($format_id);
    $settings = $editor->getSettings();
    $this->assertIdentical($editor->getEditor(), 'unicorn', 'The text editor is configured correctly.');
    $this->assertIdentical($settings['foo'], $foo, 'The text editor settings are stored correctly.');
    $this->assertIdentical($settings['ponies too'], true, 'The text editor defaults are retrieved correctly.');
    $this->assertIdentical($settings['rainbows'], true, 'The text editor defaults added by hook_editor_settings_defaults() are retrieved correctly.');
    $this->assertIdentical($settings['sparkles'], false, 'The text editor defaults modified by hook_editor_settings_defaults_alter() are retrieved correctly.');
    $this->drupalGet('admin/config/content/formats/manage/'. $format_id);
    $select = $this->xpath('//select[@name="editor[editor]"]');
    $select_is_disabled = $this->xpath('//select[@name="editor[editor]" and @disabled="disabled"]');
    $options = $this->xpath('//select[@name="editor[editor]"]/option');
    $this->assertTrue(count($select) === 1, 'The Text Editor select exists.');
    $this->assertTrue(count($select_is_disabled) === 0, 'The Text Editor select is not disabled.');
    $this->assertTrue(count($options) === 2, 'The Text Editor select has two options.');
    $this->assertTrue(((string) $options[1]['selected']) === 'selected', 'Option 2 ("Unicorn Editor") is selected.');
  }

}
