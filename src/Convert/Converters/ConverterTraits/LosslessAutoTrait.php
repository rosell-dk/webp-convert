<?php

//namespace WebPConvert\Convert\BaseConverters\BaseTraits;
namespace WebPConvert\Convert\Converters\ConverterTraits;

/**
 * Trait for converters that supports lossless encoding and thus the "lossless:auto" option.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
trait LosslessAutoTrait
{

    public function supportsLossless()
    {
        return true;
    }

    /** Default is to not pass "lossless:auto" on, but implement it.
     *
     *  The Stack converter passes it on (it does not even use this trait)
     *  WPC currently implements it, but this might be configurable in the future.
     *
     */
    public function passOnLosslessAuto()
    {
        return false;
    }

    protected function runActualConvert()
    {
        if (!$this->passOnLosslessAuto() && ($this->options['lossless'] === 'auto') && $this->supportsLossless()) {
            $destination = $this->destination;
            $destinationLossless =  $this->destination . '.lossless.webp';
            $destinationLossy =  $this->destination . '.lossy.webp';

            $this->logLn(
                'Lossless is set to auto. Converting to both lossless and lossy and selecting the smallest file'
            );


            $this->ln();
            $this->logLn('Converting to lossy');
            $this->destination = $destinationLossy;
            $this->options['lossless'] = false;
            $this->doActualConvert();
            $this->logLn('Reduction: ' .
                round((filesize($this->source) - filesize($this->destination))/filesize($this->source) * 100) . '% ');

            $this->ln();
            $this->logLn('Converting to lossless');
            $this->destination = $destinationLossless;
            $this->options['lossless'] = true;
            $this->doActualConvert();
            $this->logLn('Reduction: ' .
                round((filesize($this->source) - filesize($this->destination))/filesize($this->source) * 100) . '% ');

            $this->ln();
            if (filesize($destinationLossless) > filesize($destinationLossy)) {
                $this->logLn('Picking lossy');
                unlink($destinationLossless);
                rename($destinationLossy, $destination);
            } else {
                $this->logLn('Picking lossless');
                unlink($destinationLossy);
                rename($destinationLossless, $destination);
            }
            $this->destination = $destination;
        } else {
            $this->doActualConvert();
        }
    }
}
