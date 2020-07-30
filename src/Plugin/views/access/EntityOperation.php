<?php

namespace Drupal\veoa\Plugin\views\access;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\Routing\Route;

/**
 * Access plugin checking if the current user can operate on an entity.
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "veoa_entity_access_operation",
 *   title = @Translation("Entity Operation"),
 *   help = @Translation("Provides a Views access control plugin checking if the user can perform an operation on an entity.")
 * )
 */
class EntityOperation extends AccessPluginBase {

  /**
   * Overrides Drupal\views\Plugin\Plugin::$usesOptions.
   */
  protected $usesOptions = TRUE;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type repository.
   *
   * @var \Drupal\Core\Entity\EntityTypeRepositoryInterface
   */
  protected $entityTypeRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityTypeRepository = $container->get('entity_type.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * All validation done in route. Must be TRUE or controller will render an
   * empty page.
   */
  public function access(AccountInterface $account) {
    return $this->isValidConfig();
  }

  /**
   * {@inheritdoc}
   *
   * This method is called when the view is saved.
   */
  public function alterRouteDefinition(Route $route) {
    if ($this->isValidConfig()) {
      $parameter = $this->options['parameter'];
      $entity_type = $this->options['entity_type'];
      $operation = $this->options['operation'];

      $options = $route->getOptions();
      $options['parameters'][$parameter]['type'] = 'entity:' . $entity_type;
      $route
        ->setRequirement('_entity_access', $entity_type . '.' . $operation)
        ->setOptions($options);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    if ($this->isValidConfig()) {
      if ($entity_type = $this->entityTypeManager->getDefinition($this->options['entity_type'], FALSE)) {
        return $this->t('@entity_type: %operation', [
          '@entity_type' => $entity_type->getLabel(),
          '%operation' => $this->options['operation'],
        ]);
      }
      else {
        return $this->t('Missing entity type');
      }
    }
    else {
      return $this->t('Not defined');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['parameter'] = ['default' => ''];
    $options['entity_type'] = ['default' => ''];
    $options['operation'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['parameter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Parameter name'),
      '#default_value' => $this->options['parameter'],
      '#field_prefix' => '%',
      '#description' => $this->t("The parameter found in the path. For example: '@parameter' found in path '@path'", [
        '@parameter' => '%node',
        '@path' => 'node/%node/edit',
      ]),
    ];

    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#default_value' => $this->options['entity_type'],
      '#options' => $this->entityTypeRepository->getEntityTypeLabels(TRUE),
      '#empty_option' => $this->t('- None -'),
      '#empty_value' => '',
    ];

    $form['operation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Operation'),
      '#default_value' => $this->options['operation'],
      '#description' => $this->t('Checks if the current user has access to execute an operation on an event. Common operations include: view, update, create, delete.'),
    ];
  }

  protected function isValidConfig() {
    $entity_types = $this->entityTypeRepository->getEntityTypeLabels();
    return
      !empty($this->options['parameter']) &&
      !empty($this->options['entity_type']) &&
      array_key_exists($this->options['entity_type'], $entity_types) &&
      !empty($this->options['operation']);
  }

}
