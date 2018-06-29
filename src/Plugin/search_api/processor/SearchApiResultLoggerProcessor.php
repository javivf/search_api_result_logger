<?php

namespace Drupal\search_api_result_logger\Plugin\search_api\processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\Item;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Query\ResultSetInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Log querys and results.
 *
 * @SearchApiProcessor(
 *   id = "search_api_result_logger_processor",
 *   label = @Translation("Search API Result Logger"),
 *   description = @Translation("Log querys and results."),
 *   stages = {
 *     "postprocess_query" = 10,
 *   }
 * )
 */
class SearchApiResultLoggerProcessor extends ProcessorPluginBase implements PluginFormInterface {

  use PluginFormTrait;

  protected $entity_manager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->entity_manager = $container->get('entity.manager');
    return $processor;
  }

  /**
   * Form constructor.
   *
   * Plugin forms are embedded in other forms. In order to know where the plugin
   * form is located in the parent form, #parents and #array_parents must be
   * known, but these are not available during the initial build phase. In order
   * to have these properties available when building the plugin form's
   * elements, let this method return a form element that has a #process
   * callback and build the rest of the form in the callback. By the time the
   * callback is executed, the element's #parents and #array_parents properties
   * will have been set by the form API. For more documentation on #parents and
   * #array_parents, see \Drupal\Core\Render\Element\FormElement.
   *
   * @param array $form
   *   An associative array containing the initial structure of the plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   *
   * @return array
   *   The form structure.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $indices = \Drupal\search_api\Entity\Index::loadMultiple();

    $options = [];
    foreach ($indices as $index) {
      $options[$index->get('id')] = $index->get('name') . ' (' . $index->get('id') . ')';
    }

    unset($options[$this->getIndex()->Id()]);

    if ($options){
      $form['indices'] = [
        '#type' => 'radios',
        '#options' => $options,
        '#title' => $this->t('Select index to save search stats'),
        '#default_value' => $this->getConfiguration()['indices'],
      ];
    }
    else {
      $form['markup'] = [
        '#type' => 'item',
        '#markup' => $this->t('Ypu have to create a new index to save search stats'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function postprocessSearchResults(ResultSetInterface $results) {
    $index = \Drupal\search_api\Entity\Index::load($this->getConfiguration()['indices']);
    $item = new Item($index, (string)time());

    $field = new Field($index, 'result_items');
    $field->addValue($results->getResultItems());
    $item->setField('result_items', $field);

    $field = new Field($index, 'timestamp');
    $field->addValue(time());
    $item->setField('timestamp', $field);

    $field = new Field($index, 'query_tags');
    $field->addValue($results->getQuery()->getTags());
    $item->setField('query_tags', $field);

    $field = new Field($index, 'query_keys');
    $field->addValue($results->getQuery()->getKeys());
    $item->setField('query_keys', $field);

    $field = new Field($index, 'extra_data');
    $field->addValue($results->getAllExtraData());
    $item->setField('extra_data', $field);

    $tmp = $index->getServerInstance()->indexItems($index, [$item]);

  }
}
