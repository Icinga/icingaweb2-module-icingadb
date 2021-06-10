<?php

namespace Icinga\Module\Icingadb\Web\Control;

use ipl\Web\Common\FormUid;
use ipl\Web\Compat\CompatForm;

class ProblemToggle extends CompatForm
{
    use FormUid;

    protected $filter;

    protected $protector;

    protected $defaultAttributes = [
        'name'    => 'problem-toggle',
        'class'   => 'icinga-form icinga-controls inline'
    ];

    public function __construct($filter)
    {
        $this->filter = $filter;
    }


    /**
     * Set callback to protect ids with
     *
     * @param   callable $protector
     *
     * @return  $this
     */
    public function setIdProtector($protector)
    {
        $this->protector = $protector;

        return $this;
    }

    protected function assemble()
    {
        $this->addElement('checkbox', 'problems', [
            'class'     => 'autosubmit',
            'id'        => $this->protectId('problems'),
            'label'     => t('Problems Only'),
            'value'     => $this->filter !== null
        ]);

        $this->add($this->createUidElement());
    }

    private function protectId($id)
    {
        if (is_callable($this->protector)) {
            return call_user_func($this->protector, $id);
        }

        return $id;
    }
}
