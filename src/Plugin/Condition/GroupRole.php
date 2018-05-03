<?php

namespace Drupal\group_role_condition\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Provides a 'Group Role' condition.
*
* @Condition(
*   id = "group_role",
*   label = @Translation("Group role"),
*   context = {
*     "group" = @ContextDefinition("entity:group", label = @Translation("group"))
*   }
* )
*
*/
class GroupRole extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates a new GroupRole instance.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage
   *   The entity storage.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(EntityStorageInterface $entity_storage, array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityStorage = $entity_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.manager')->getStorage('group_role'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }


  /**
    * {@inheritdoc}
    */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $options = [];

    // Build a list of group role labels.
    $group_roles = $this->entityStorage->loadMultiple();
    foreach ($group_roles as $role) {
      $role_id = substr($role->id(), strrpos($role->id(), '-') + 1);
      $role_label = $role->label();
      $options[$role_id] = $role_label;
    }

    // Show a series of checkboxes for group role selection.
    $form['group_roles'] = [
      '#title' => $this->t('Group roles'),
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => $this->configuration['group_roles'],
      '#description' => $this->t('If you select no Group roles, the condition will evaluate to TRUE for all requests.'),
      '#attached' => array(
        'library' => array(
          'group_role_condition/block',
        ),
      )
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
      $this->configuration['group_roles'] = array_filter($form_state->getValue('group_roles'));
      parent::submitConfigurationForm($form, $form_state);
  }

  /**
    * {@inheritdoc}
   */
  public function summary() {
    $group_roles = $this->configuration['group_roles'];

    // Format a pretty string if multiple group roles were selected.
    if (count($group_roles) > 1) {
      $last = array_pop($group_roles);
      $group_roles = implode(', ', $group_roles);
      return $this->t('The group role is @group_roles or @last', ['@group_roles' => $group_roles, '@last' => $last]);
    }

    // If just one was selected, return a simpler string.
    return $this->t('The group role is @group_role', ['@group_role' => reset($group_roles)]);
  }

  /**
   * {@inheritdoc}
    */
  public function evaluate() {
    $group_roles = $this->configuration['group_roles'];
    $group_role_storage = \Drupal::entityTypeManager()->getStorage('group_role');
    $user = \Drupal::currentUser();

    // If there are no group roles selected and the condition is not negated, we
    // return TRUE because it means all group roles are valid.
    if (empty($group_roles) && !$this->isNegated()) {
      return TRUE;
    }

    // Get Group for Block.
    $group = $this->getContextValue('group');

    // Retrieve all of the group roles the user may get for the group.
    $user_group_roles = $group_role_storage->loadByUserAndGroup($user , $group);

    // Check each retrieved role for the selected role(s).
    foreach ($user_group_roles as $user_group_role) {
      $user_group_role_id = $user_group_role->id();
      $user_group_role_id = substr($user_group_role->id(), strrpos($user_group_role->id(), '-') + 1);
      foreach ($group_roles as $group_role) {
        $pos = strrpos($group_role, '-');

        if ($pos === FALSE) {
          $group_role_id = $group_role;
        } else {
          $group_role_id = substr($group_role, $pos + 1);
        }
        if (!empty($group_role_id) && $user_group_role_id == $group_role_id) {
          return TRUE;
        }
      }
    }

    // If no matching role is found, return false
    return FALSE;
  }
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['group_roles' => []] + parent::defaultConfiguration();
  }

}
