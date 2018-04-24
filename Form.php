<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Form;

use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventListenerInterface;
use Cake\Event\EventManager;
use Cake\Form\Schema;
use Cake\Validation\ValidatorAwareInterface;
use Cake\Validation\ValidatorAwareTrait;
use ReflectionMethod;

/**
 * Form abstraction used to create forms not tied to ORM backed models,
 * or to other permanent datastores. Ideal for implementing forms on top of
 * API services, or contact forms.
 *
 * ### Building a form
 *
 * This class is most useful when subclassed. In a subclass you
 * should define the `_buildSchema`, `validationDefault` and optionally,
 * the `_execute` methods. These allow you to declare your form's
 * fields, validation and primary action respectively.
 *
 * Forms are conventionally placed in the `App\Form` namespace.
 */
class Form implements EventListenerInterface, EventDispatcherInterface, ValidatorAwareInterface
{
    use EventDispatcherTrait;
    use ValidatorAwareTrait;

    /**
     * Schema class.
     *
     * @var string
     */
    protected $_schemaClass = Schema::class;

    /**
     * The alias this object is assigned to validators as.
     *
     * @var string
     */
    const VALIDATOR_PROVIDER_NAME = 'form';

    /**
     * The name of the event dispatched when a validator has been built.
     *
     * @var string
     */
    const BUILD_VALIDATOR_EVENT = 'Form.buildValidator';

    /**
     * The schema used by this form.
     *
     * @var \Cake\Form\Schema
     */
    protected $_schema;

    /**
     * The errors if any
     *
     * @var array
     */
    protected $_errors = [];

    /**
     * The validator used by this form.
     *
     * @var \Cake\Validation\Validator
     */
    protected $_validator;

    /**
     * Constructor
     *
     * @param \Cake\Event\EventManager|null $eventManager The event manager.
     *  Defaults to a new instance.
     */
    public function __construct(EventManager $eventManager = null)
    {
        if ($eventManager !== null) {
            $this->setEventManager($eventManager);
        }

        $this->getEventManager()->on($this);
    }

    /**
     * Get the Form callbacks this form is interested in.
     *
     * The conventional method map is:
     *
     * - Form.buildValidator => buildValidator
     *
     * @return array
     */
    public function implementedEvents()
    {
        if (method_exists($this, 'buildValidator')) {
            return [
                self::BUILD_VALIDATOR_EVENT => 'buildValidator',
            ];
        }

        return [];
    }

    /**
     * Get/Set the schema for this form.
     *
     * This method will call `_buildSchema()` when the schema
     * is first built. This hook method lets you configure the
     * schema or load a pre-defined one.
     *
     * @param \Cake\Form\Schema|null $schema The schema to set, or null.
     * @return \Cake\Form\Schema the schema instance.
     */
    public function schema(Schema $schema = null)
    {
        if ($schema === null && empty($this->_schema)) {
            $schema = $this->_buildSchema(new $this->_schemaClass);
        }
        if ($schema) {
            $this->_schema = $schema;
        }

        return $this->_schema;
    }

    /**
     * A hook method intended to be implemented by subclasses.
     *
     * You can use this method to define the schema using
     * the methods on Cake\Form\Schema, or loads a pre-defined
     * schema from a concrete class.
     *
     * @param \Cake\Form\Schema $schema The schema to customize.
     * @return \Cake\Form\Schema The schema to use.
     */
    protected function _buildSchema(Schema $schema)
    {
        return $schema;
    }

    /**
     * Used to check if $data passes this form's validation.
     *
     * @param array $data The data to check.
     * @return bool Whether or not the data is valid.
     */
    public function validate(array $data)
    {
        $validator = $this->getValidator();
        $this->_errors = $validator->errors($data);

        return count($this->_errors) === 0;
    }

    /**
     * Get the errors in the form
     *
     * Will return the errors from the last call
     * to `validate()` or `execute()`.
     *
     * @return array Last set validation errors.
     */
    public function errors()
    {
        return $this->_errors;
    }

    /**
     * Set the errors in the form.
     *
     * ```
     * $errors = [
     *      'field_name' => ['rule_name' => 'message']
     * ];
     *
     * $form->setErrors($errors);
     * ```
     *
     * @since 3.5.1
     * @param array $errors Errors list.
     * @return $this
     */
    public function setErrors(array $errors)
    {
        $this->_errors = $errors;

        return $this;
    }

    /**
     * Execute the form if it is valid.
     *
     * First validates the form, then calls the `_execute()` hook method.
     * This hook method can be implemented in subclasses to perform
     * the action of the form. This may be sending email, interacting
     * with a remote API, or anything else you may need.
     *
     * @param array $data Form data.
     * @return bool False on validation failure, otherwise returns the
     *   result of the `_execute()` method.
     */
    public function execute(array $data)
    {
        if (!$this->validate($data)) {
            return false;
        }

        return $this->_execute($data);
    }

    /**
     * Hook method to be implemented in subclasses.
     *
     * Used by `execute()` to execute the form's action.
     *
     * @param array $data Form data.
     * @return bool
     */
    protected function _execute(array $data)
    {
        return true;
    }

    /**
     * Get the printable version of a Form instance.
     *
     * @return array
     */
    public function __debugInfo()
    {
        $special = [
            '_schema' => $this->schema()->__debugInfo(),
            '_errors' => $this->errors(),
            '_validator' => $this->getValidator()->__debugInfo()
        ];

        return $special + get_object_vars($this);
    }
}
