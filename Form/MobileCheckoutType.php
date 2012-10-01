<?php

namespace Cariboo\Payment\VirgopassBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Type for Virgopass Mobile Checkout.
 *
 * @author Stephane Decleire <sdecleire@cariboo-networks.com>
 */
class MobileCheckoutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
    }

    public function getName()
    {
        return 'virgopass_mobile_checkout';
    }
}