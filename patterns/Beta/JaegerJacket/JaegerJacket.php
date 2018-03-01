<?php
/** Freesewing\Patterns\Beta\JaegerJacket class */
namespace Freesewing\Patterns\Beta;

use Freesewing\Utils;
use Freesewing\BezierToolbox;

/**
 * A sport coat or single-breasted jacket pattern
 *
 * @author Joost De Cock <joost@decock.org>
 * @copyright 2017 Joost De Cock
 * @license http://opensource.org/licenses/GPL-3.0 GNU General Public License, Version 3
 */
class JaegerJacket extends \Freesewing\Patterns\Beta\BentBodyBlock
{
    /*
        ___       _ _   _       _ _
       |_ _|_ __ (_) |_(_) __ _| (_)___  ___
        | || '_ \| | __| |/ _` | | / __|/ _ \
        | || | | | | |_| | (_| | | \__ \  __/
       |___|_| |_|_|\__|_|\__,_|_|_|___/\___|

      Things we need to do before we can draft a pattern
    */

    /** Lenght bonus below hips as factor as fraction of neck to hip = 19% */
    const LENGTHEN_FACTOR = 0.19;

    /** Front extension as factor of chest circumference = 2% */
    const FRONT_EXTENSION = 0.02;

    /** Sleeve vent */
    const SLEEVE_VENT_LENGTH = 100;
    const SLEEVE_VENT_WIDTH = 40;

    /** Inner pocket */
    const INNER_POCKET_WIDTH = 125;
    const INNER_POCKET_DEPTH = 160;
    const INNER_POCKET_WELT = 5;
    
    /** Inner pocket */
    const CHEST_POCKET_DEPTH = 120;
    
    /** Pocket */
    const POCKET_FOLDOVER = 40;

    /**
     * Sets up options and values for our draft
     *
     * By branching this out of the sample/draft methods, we can
     * set a bunch of options and values the influence the draft
     * without having to touch the sample/draft methods
     * When extending this pattern so we can just implement the
     * initialize() method and re-use the other methods.
     *
     * Good to know:
     * Options are typically provided by the user, but sometimes they are fixed
     * Values are calculated for re-use later
     *
     * @param \Freesewing\Model $model The model to sample for
     *
     * @return void
     */
    public function initialize($model)
    {
        parent::initialize($model);

        // Length bonus
        $this->setValueIfUnset('lengthBase', $model->m('centerBackNeckToWaist') + $model->m('naturalWaistToHip'));
        $this->setValueIfUnset('lengthBonus', $this->v('lengthBase') * self::LENGTHEN_FACTOR + $this->o('lengthBonus'));
        // Overwrite lengthBonus option with new value
        $this->setOption('lengthBonus', $this->v('lengthBonus'));

        // Front extension
        $this->setValueIfUnset('frontExtension', self::FRONT_EXTENSION);

        // Sleeve vent
        $this->setValueIfUnset('sleeveVentLength', self::SLEEVE_VENT_LENGTH);
        $this->setValueIfUnset('sleeveVentWidth', self::SLEEVE_VENT_WIDTH);
        
        // Make sure collar height makes sense
        if($this->o('collarHeight')*2 < $this->o('rollLineCollarHeight')) $this->setValue('collarHeight', $this->o('rollLineCollarHeight')/2);
        else $this->setValue('collarHeight', $this->o('collarHeight'));

        // Prevent chest shaping from being 0, because that will get read as 360 degrees
        if($this->o('chestShaping') == 0) $this->setOptionIfUnset('chestShaping', 0.0001);

        // We store all reduction in values to avoid option/value mix as side is not an option
        $this->setValueIfUnset('waistReductionRatioBack', $this->o('waistReductionRatioBack'));
        $this->setValueIfUnset('waistReductionRatioFront', $this->o('waistReductionRatioFront'));
        $sideReduction = 1 - ($this->o('waistReductionRatioFront') + $this->o('waistReductionRatioBack'));
        $this->setValueIfUnset('waistReductionRatioFrontSide', $sideReduction/2);
        $this->setValueIfUnset('waistReductionRatioBackSide', $sideReduction/2);

        $this->setValueIfUnset('hipsReductionRatioBack', $this->o('hipsReductionRatioBack'));
        $sideReduction = 1 - $this->o('hipsReductionRatioBack');
        $this->setValueIfUnset('hipsReductionRatioFrontSide', $sideReduction/2);
        $this->setValueIfUnset('hipsReductionRatioBackSide', $sideReduction/2);
        
        // Helper values
        $chest = $model->m('chestCircumference') + $this->o('chestEase');
        $waist = $model->m('naturalWaist') + $this->o('waistEase');
        $hips = $model->m('hipsCircumference') + $this->o('hipsEase');
        $this->setValueIfUnset('quarterChest', $chest/4);
        $this->setValueIfUnset('quarterWaist', $waist/4);
        $this->setValueIfUnset('quarterHips', $hips/4);

        // Actual reduction values
        $this->setValueIfUnset('waistReduction', ($chest - $waist));
        $this->setValueIfUnset('waistReductionBack',      $this->v('waistReduction') * $this->v('waistReductionRatioBack'));
        $this->setValueIfUnset('waistReductionFront',     $this->v('waistReduction') * $this->v('waistReductionRatioFront'));
        $this->setValueIfUnset('waistReductionFrontSide', $this->v('waistReduction') * $this->v('waistReductionRatioFrontSide'));
        $this->setValueIfUnset('waistReductionBackSide',  $this->v('waistReduction') * $this->v('waistReductionRatioBackSide'));
        $this->setValueIfUnset('hipsReduction', ($chest - $hips));
        $this->setValueIfUnset('hipsReductionBack',      $this->v('hipsReduction') * $this->v('hipsReductionRatioBack'));
        $this->setValueIfUnset('hipsReductionFrontSide', $this->v('hipsReduction') * $this->v('hipsReductionRatioFrontSide'));
        $this->setValueIfUnset('hipsReductionBackSide',  $this->v('hipsReduction') * $this->v('hipsReductionRatioBackSide'));

        // And now these values divided to make life simpler
        $this->setValueIfUnset('redBackWaist', $this->v('waistReductionBack')/2); // 50% coz cut twice. This is the full shaping on 1 back.
        $this->setValueIfUnset('redFrontWaist', $this->v('waistReductionFront')/2); // 50% coz cut twice. This is the full dart width on 1 front.
        $this->setValueIfUnset('redFrontSideWaist', $this->v('waistReductionFrontSide')/4); // 25% coz cut twice and divided between front and side. This is the full shaping on 1 [front/side].
        $this->setValueIfUnset('redBackSideWaist', $this->v('waistReductionBackSide')/4); // 25% coz cut twice and divided between back and side. This is the full shaping on 1 [back/side].
        $this->setValueIfUnset('redBackHips', $this->v('hipsReductionBack')/2); // 50% coz cut twice. This is the full shaping on 1 back.
        $this->setValueIfUnset('redFrontSideHips', $this->v('hipsReductionFrontSide')/4); // 25% coz cut twice and divided between front and side. This is the full shaping on 1 [front/side].
        $this->setValueIfUnset('redBackSideHips', $this->v('hipsReductionBackSide')/4); // 25% coz cut twice and divided between back and side. This is the full shaping on 1 [back/side].

    }

    /*
        ____             __ _
       |  _ \ _ __ __ _ / _| |_
       | | | | '__/ _` | |_| __|
       | |_| | | | (_| |  _| |_
       |____/|_|  \__,_|_|  \__|

      The actual sampling/drafting of the pattern
    */

    /**
     * Generates a draft of the pattern
     *
     * This creates a draft of this pattern for a given model
     * and set of options. You get a complete pattern with
     * all bels and whistles.
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function draft($model)
    {
        $this->sample($model);

        // Finalize pattern parts
        $this->finalizeBack($model);
        $this->finalizeFront($model);
        $this->finalizeSide($model);
        $this->finalizeTopsleeve($model);
        $this->finalizeUndersleeve($model);
        $this->finalizeUndercollar($model);
        $this->finalizeCollar($model);
        $this->finalizeCollarstand($model);
        
        $this->finalizePocket($model);
        $this->finalizeChestPocketWelt($model);
        $this->finalizeChestPocketBag($model);
        $this->finalizeInnerPocketWelt($model);
        $this->finalizeInnerPocketBag($model);
        $this->finalizeInnerPocketFacingExtension($model);

        // Is this a paperless pattern?
        if ($this->isPaperless) {
            // Add paperless info to all parts
            $this->paperlessBack($model);
            $this->paperlessFront($model);
            $this->paperlessSide($model);
            $this->paperlessTopsleeve($model);
            $this->paperlessUndersleeve($model);
            $this->paperlessUndercollar($model);
            $this->paperlessCollar($model);
            $this->paperlessCollarstand($model);
            $this->paperlessPocket($model);
            $this->paperlessChestPocketWelt($model);
            $this->paperlessChestPocketBag($model);
            $this->paperlessInnerPocketWelt($model);
            $this->paperlessInnerPocketBag($model);
            $this->paperlessInnerPocketFacingExtension($model);
        }
    }
    
    protected function armholeLen()
      {
         /** @var \Freesewing\Part $back */
          $back = $this->parts['back'];
         /** @var \Freesewing\Part $front */
          $front = $this->parts['front'];
         /** @var \Freesewing\Part $side */
          $side = $this->parts['side'];

          return (  
              $back->curveLen(12, 19, 17, 10) + $back->curveLen(10, 18, 15, 14) +
              $side->curveLen('side14', 'side16', 'side13',5) + $side->curveLen(5,'5CpLeft','slArmCpRight','slArm') +
              $front->curveLen(12, 19, 17, 10) + $front->curveLen(10, 18, 15, 14) + $front->curveLen(14, '14CpRight', 'slArmCpLeft', 'slArm')
          );
     }

    /**
     * Generates a sample of the pattern
     *
     * This creates a sample of this pattern for a given model
     * and set of options. You get a barebones pattern with only
     * what it takes to illustrate the effect of changes in
     * the sampled option or measurement.
     *
     * @param \Freesewing\Model $model The model to sample for
     *
     * @return void
     */
    public function sample($model)
    {
        $this->initialize($model);

        // Draft front and back blocks
        $this->draftBackBlock($model);
        $this->draftFrontBlock($model);
        
        // Draft front and back parts
        $this->draftFront($model);
        $this->draftBack($model);
        $this->draftSide($model);
        $this->draftCollar($model);
        $this->draftCollarstand($model);
        $this->draftUndercollar($model);
        
        $this->draftPocket($model);
        $this->draftChestPocketWelt($model);
        $this->draftChestPocketBag($model);
        $this->draftInnerPocketWelt($model);
        $this->draftInnerPocketBag($model);
        $this->draftInnerPocketFacingExtension($model);
        
        // Draft sleeve 
        // Tweak the sleeve until it fits the armhole
        do {
            $this->draftSleeveBlock($model);
        } while (abs($this->armholeDelta()) > 1 && $this->v('sleeveTweakRun') < 50);
        $this->msg('After '.$this->v('sleeveTweakRun').' attemps, the sleeve head is '.round($this->armholeDelta(),1).'mm off.');
        $this->draftTopsleeveBlock($model);
        $this->draftUndersleeveBlock($model);
        $this->draftTopsleeve($model);
        $this->draftUndersleeve($model);
        

        // Hide blocks
        $this->parts['backBlock']->setRender(false);
        $this->parts['frontBlock']->setRender(false);
        $this->parts['sleeveBlock']->setRender(false);
        $this->parts['topsleeveBlock']->setRender(false);
        $this->parts['undersleeveBlock']->setRender(false);
    }

    /**
     * Drafts the back block
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function draftBackBlock($model)
    {
        parent::draftBackBlock($model);

        /** @var \Freesewing\Part $p */
        $p = $this->parts['backBlock'];

        // Widest part is a bit above chest line, so let's add a point there
        $p->newPoint('chestCenter', 0, $p->y(18));

        // Center back neck reduction
        $p->newPoint('centerBackNeck', $model->m('chestCircumference') * $this->o('neckReduction')/2, $p->y(1));
        $p->addPoint('chestCenterCpTop', $p->shift('chestCenter',90, $p->deltaY('centerBackNeck', 'chestCenter')/2));

        // Draw style line (sl) seperating the side panel
        $p->clonePoint(14, 'slArm');
        $p->addPoint('slArmCpBottom', $p->rotate(15,14,90));
        $p->addPoint('slChestCpTop', $p->shift('slArmCpBottom',-90, 30));
        $p->addPoint('slChest', $p->shift('slArmCpBottom',-90, 60));
        $p->newPoint('slWaist', $p->x('slChest'), $p->y(3));
        $p->newPoint('slHips', $p->x('slChest'), $p->y(3)+$model->m('naturalWaistToHip'));
        $p->newPoint('slHem', $p->x('slChest'), $p->y(4));

        // Shaping at back seam
        $p->addPoint('waistCenter', $p->shift(3, 0, $this->v('redBackWaist')));
        $p->newPoint('hipsCenter', $this->v('redBackHips'), $p->y(3) + $model->m('naturalWaistToHip'));
        $p->newPoint('hemCenter', $p->x('hipsCenter'), $p->y(4));
        $p->addPoint('chestCenterCpBottom', $p->shift('chestCenter', -90, $p->deltaY('chestCenter',3)/3));
        $p->addPoint('waistCenterCpTop', $p->shift('waistCenter', 90, $p->deltaY('chestCenter',3)/3));
        $p->addPoint('waistCenterCpBottom', $p->shift('waistCenter', -90, $p->deltaY(3,'hipsCenter')/3));
        $p->addPoint('hipsCenterCpTop', $p->shift('hipsCenter', 90, $p->deltaY(3,'hipsCenter')/3));

        // Shaping at back/side seam
        $p->addPoint('waistBackSide', $p->shift('slWaist', 180, $this->v('redBackSideWaist')));
        $p->addPoint('waistSideBack', $p->shift('slWaist', 0, $this->v('redBackSideWaist')));
        $p->addPoint('hipsBackSide', $p->shift('slHips',  180, $this->v('redBackSideHips')));
        $p->addPoint('hipsSideBack', $p->shift('slHips',  0, $this->v('redBackSideHips')));
        $p->newPoint('hemBackSide', $p->x('hipsBackSide'), $p->y(4));
        $p->newPoint('hemSideBack', $p->x('hipsSideBack'), $p->y(4));
        $p->newPoint('hipsBackSideCpTop', $p->x('hipsBackSide'), $p->y('hipsCenterCpTop'));
        $p->newPoint('hipsSideBackCpTop', $p->x('hipsSideBack'), $p->y('hipsCenterCpTop'));
        $p->newPoint('waistBackSideCpBottom', $p->x('waistBackSide'), $p->y('waistCenterCpBottom'));
        $p->newPoint('waistSideBackCpBottom', $p->x('waistSideBack'), $p->y('waistCenterCpBottom'));
        $p->addPoint('waistBackSideCpTop', $p->shift('waistBackSide', 90, $p->deltaY('slArm','waistSideBack')/3));
        $p->addPoint('waistSideBackCpTop', $p->shift('waistSideBack', 90, $p->deltaY('slArm','waistSideBack')/3));


        // Paths
        $path = 'M centerBackNeck C centerBackNeck chestCenterCpTop chestCenter L 2 L 3 L 4 L 6 L 5 C 13 16 14 C 15 18 10 C 17 19 12 L 8 C 20 1 1 z';
        $p->newPath('seamline', $path, ['class' => 'hint']);
        $p->newPath('styleline', 'M slArm C slArmCpBottom slChestCpTop slChest L slHem', ['class' => 'hint']);
        $p->newPath('back','
            M centerBackNeck 
            C centerBackNeck 20 8
            L 12
            C 19 17 10
            C 18 15 14
            C slArmCpBottom waistBackSideCpTop waistBackSide
            C waistBackSideCpBottom hipsBackSideCpTop hipsBackSide
            L hemBackSide
            L hemCenter
            L hipsCenter 
            C hipsCenterCpTop waistCenterCpBottom waistCenter 
            C waistCenterCpTop chestCenterCpBottom chestCenter
            C chestCenterCpTop centerBackNeck centerBackNeck
            z
            ', ['class' => 'fabric']);
        $p->newPath('side','
            M hemSideBack
            L hipsSideBack
            C hipsSideBackCpTop waistSideBackCpBottom waistSideBack
            C waistSideBackCpTop slArmCpBottom slArm
            C 16 13 5
            L 6
            L hemSideBack 
            z
            ', ['class' => 'help']);

        // Mark path for sample service
        $p->paths['back']->setSample(true);

        // Store length of the collar
        $this->setValue('backCollarLength', $p->curveLen(8,20,'centerBackNeck','centerBackNeck'));
    }

    /**
     * Drafts the front block
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function draftFrontBlock($model)
    {
        // Note: The parent method called below will start by cloning all point from the backBlock
        parent::draftFrontBlock($model);
        /** @var \Freesewing\Part $p */
        $p = $this->parts['frontBlock'];
        
        // Draw style line (sl) seperating the side panel
        $p->curveCrossesX(5,13,16,14,$p->x(13), '.tmp');
        $p->clonePoint('.tmp1','slArm');
        // Need control points for this splitted curve
        $p->splitCurve(5,13,16,14,'slArm','.tmp');
        $p->clonePoint('.tmp7', 'slArmCpLeft');
        $p->clonePoint('.tmp3', 'slArmCpRight');
        $p->clonePoint('.tmp2', '5CpLeft');
        $p->clonePoint('.tmp6', '14CpRight');

        $p->newPoint('slWaist', $p->x(5) - $model->m('chestCircumference') * $this->o('sideFrontPlacement'), $p->y(3));
        $p->addPoint('slWaistCpTop', $p->shift('slWaist', 90, $p->deltaY(5,3)/2));
        $p->newPoint('slHips', $p->x('slWaist'), $p->y(3)+$model->m('naturalWaistToHip'));
        $p->newPoint('slHem', $p->x('slWaist'), $p->y(4));

        // Shift sideseam shaping to adapt for different in location of style line
        $shiftThese = [
            'hemBackSide', 
            'hemSideBack', 
            'hipsBackSide', 
            'hipsSideBack',
            'hipsBackSideCpTop', 
            'hipsSideBackCpTop',
            'waistBackSideCpBottom', 
            'waistSideBackCpBottom',
            'waistBackSide', 
            'waistSideBack',
            'waistBackSideCpTop', 
            'waistSideBackCpTop',
        ];
        $shiftDistance = $p->deltaX('slChest', 'slWaist');
        foreach($shiftThese as $pid) $p->addPoint($pid, $p->shift($pid, 0, $shiftDistance));
    
        // Bring over side panel points from back block
        $b =  $this->parts['backBlock'];
        $transferThese = [13,16,14,'slArmCpBottom','waistSideBackCpTop','waistSideBack','waistSideBackCpBottom','hipsSideBackCpTop','hipsSideBack','hemSideBack'];
        foreach($transferThese as $pid) $p->newPoint('side'.ucfirst($pid), $b->x(5) + $b->deltaX($pid,5), $b->y(5) + $b->deltaY(5,$pid));

        // Front dart
        $p->addPoint('frontDartMid', $p->shift(3,0,$model->m('chestCircumference') * $this->o('frontDartPlacement')));
        $p->addPoint('frontDartTop', $p->shift('frontDartMid', 90, $p->deltaY(5,3)/1.5));
        $p->addPoint('frontDartBottom', $p->shift('frontDartMid', -90, $p->deltaY(3,'hipsCenter')/2));
        $p->addPoint('frontDartRight', $p->shift('frontDartMid', 0, $this->v('redFrontWaist')/2));
        $p->addPoint('frontDartLeft', $p->shift('frontDartMid', 180, $this->v('redFrontWaist')/2));
        $p->addPoint('frontDartRightCpTop', $p->shift('frontDartRight', 90, $p->deltaY('frontDartTop','frontDartMid')/3));
        $p->addPoint('frontDartLeftCpTop', $p->shift('frontDartLeft', 90, $p->deltaY('frontDartTop','frontDartMid')/3));
        $p->addPoint('frontDartRightCpBottom', $p->shift('frontDartRight', -90, $p->deltaY('frontDartMid','frontDartBottom')/3));
        $p->addPoint('frontDartLeftCpBottom', $p->shift('frontDartLeft', -90, $p->deltaY('frontDartMid','frontDartBottom')/3));

        // Drop hem center front
        $p->newPoint('cfHem', $p->x(4), $p->y(4) + ($model->m('centerBackNeckToWaist') + $model->m('naturalWaistToHip')) * $this->o('centerFrontHemDrop'));
        $p->addPoint('frontSideHem', $p->beamsCross('hipsBackSide','hemBackSide','cfHem','sideHemSideBack'));
        $p->newPoint('sideFrontHem', $p->x('hemSideBack'), $p->y('frontSideHem'));



        $path = 'M 9 L 2 L 3 L 4 L 6 L 5 C 13 16 14 C 15 18 10 C 17 19 12 L 8 C 20 21 9 z';
        $p->newPath('seamline', $path, ['class' => 'hint']);
        $p->newPath('styleline', 'M slArm C slArm slWaistCpTop slWaist L slHem', ['class' => 'hint']);
        $p->newPath('front','
            M 9 
            L cfHem
            L frontSideHem
            L hipsBackSide
            C hipsBackSideCpTop waistBackSideCpBottom waistBackSide
            C waistBackSideCpTop slArm slArm
            C slArmCpLeft 14CpRight 14
            C 15 18 10
            C 17 19 12
            L 8
            C 20 21 9
            z
            M frontDartBottom 
            C frontDartBottom frontDartRightCpBottom frontDartRight
            C frontDartRightCpTop frontDartTop frontDartTop
            C frontDartTop frontDartLeftCpTop frontDartLeft
            C frontDartLeftCpBottom frontDartBottom frontDartBottom
            z
            ', ['class' => 'fabric']);
        $p->newPath('side','
            M sideFrontHem
            L hipsSideBack
            C hipsSideBackCpTop waistSideBackCpBottom waistSideBack
            C waistSideBackCpTop slArm slArm
            C slArmCpRight 5CpLeft 5
            C side13 side16 side14
            C sideSlArmCpBottom sideWaistSideBackCpTop sideWaistSideBack
            C sideWaistSideBackCpBottom sideHipsSideBackCpTop sideHipsSideBack
            L sideHemSideBack
            L sideFrontHem
            z
            ', ['class' => 'fabric']);
        
        // Mark paths for sample service
        $p->paths['front']->setSample(true);
        $p->paths['side']->setSample(true);
    }

    /**
     * Drafts the front
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function draftFront($model)
    {
        $this->clonePoints('frontBlock', 'front');

        /** @var \Freesewing\Part $p */
        $p = $this->parts['front'];

        // Front extension (fe)
        $p->newPoint('feTop', -1 * ($this->v('frontExtension') * $model->m('chestCircumference')), $p->y(9));
        $p->newPoint('feBottom', $p->x('feTop'), $p->y('cfHem'));

        // Chest pocket (cp)
        $width = $this->o('chestPocketWidth');
        $p->newPoint('cpBottomLeft', $model->m('chestCircumference')/4 * $this->o('chestPocketPlacement') - ($width/2), $p->y(5));
        $p->addPoint('cpTopLeft', $p->shift('cpBottomLeft', 90, $this->o('chestPocketWeltSize')));
        $p->addPoint('cpBottomRight', $p->shift('cpBottomLeft', 0, $width));
        $p->addPoint('cpTopRight', $p->shift('cpTopLeft', 0, $width));
        if($this->o('chestPocketAngle') > 0) {
            $p->addPoint('cpBottomRight', $p->rotate('cpBottomRight', 'cpBottomLeft', $this->o('chestPocketAngle')));
            $p->addPoint('cpTopRight', $p->rotate('cpTopRight', 'cpBottomLeft', $this->o('chestPocketAngle')));
            $p->addPoint('cpTopLeft', $p->rotate('cpTopLeft', 'cpBottomLeft', $this->o('chestPocketAngle')));
            // Make pocket parallelogram rather than rectangle
            $p->addPoint('.help', $p->shift('cpBottomLeft', 90, 5));
            $p->addPoint('cpTopLeft', $p->beamsCross('cpBottomLeft','.help','cpTopLeft','cpTopRight'));
            $p->clonePoint('cpTopRight', 'cpTopRightOrig');
            $p->addPoint('cpTopRight', $p->shiftTowards('cpTopLeft','cpTopRight', $this->o('chestPocketWidth')));
        }
        

        // Front pocket (fp)
        $width = $model->m('chestCircumference') * $this->o('frontPocketWidth');
        $p->newPoint('fpTopLeft', $model->m('chestCircumference') * $this->o('frontPocketPlacement') - ($width/2), $p->y('frontDartMid') + ($model->m('naturalWaistToHip') * $this->o('frontPocketHeight')));
        $p->addPoint('fpBottomLeft', $p->shift('fpTopLeft', -90, ($p->deltaY('frontDartMid', 'slHem') * $this->o('frontPocketDepth'))));
        $p->addPoint('fpTopRight', $p->shift('fpTopLeft', 0, $width));
        $p->addPoint('fpBottomRight', $p->shift('fpBottomLeft', 0, $width));
        // Store front pocket depth
        $this->setValue('frontPocketDepth', $p->distance('fpTopLeft','fpBottomLeft'));
        // Adapt width according to dart
        if($p->y('fpTopLeft') < $p->y('frontDartBottom')) {
            $p->curveCrossesY('frontDartLeft', 'frontDartLeftCpBottom', 'frontDartBottom', 'frontDartBottom', $p->y('fpTopLeft'), 'dartPocketLeft');
            $p->curveCrossesY('frontDartRight', 'frontDartRightCpBottom', 'frontDartBottom', 'frontDartBottom', $p->y('fpTopLeft'), 'dartPocketRight');
            $delta = $p->distance('dartPocketLeft1', 'dartPocketRight1');
            $p->addPoint('fpTopRight', $p->shift('fpTopRight', 0, $delta));
            $p->newPath('frontPocket', 'M dartPocketRight1 L fpTopRight M fpBottomRight L fpBottomLeft L fpTopLeft L dartPocketLeft1', ['class' => 'help']);

        } else {
            $p->newPath('frontPocket', 'M fpTopLeft L fpTopRight M fpBottomRight L fpBottomLeft L fpTopLeft', ['class' => 'help']);
        }

        // Inner pocket
        $p->newPoint('ipTopLeft', $p->x('waistBackSide')/2 - self::INNER_POCKET_WIDTH/2, $p->y('frontDartTop')-20);
        $p->addPoint('ipTopRight', $p->shift('ipTopLeft', 0, self::INNER_POCKET_WIDTH));
        $p->addPoint('ipMidLeft', $p->shift('ipTopLeft', -90, self::INNER_POCKET_WELT));
        $p->addPoint('ipBotLeft', $p->shift('ipMidLeft', -90, self::INNER_POCKET_WELT));
        $p->addPoint('ipMidRight', $p->shift('ipTopRight', -90, self::INNER_POCKET_WELT));
        $p->addPoint('ipBotRight', $p->shift('ipMidRight', -90, self::INNER_POCKET_WELT));


        /*
         * Slash & spread chest.  This is one of those things that's simpler on paper
         *
         * We could slash this part into a limited number of strips here (say 5 or so)
         * and then rotate them all. However, that would require us to split the curves
         * into 5 parts, which is particularly challenging for the armhole that itself is
         * made up of 3 curves strung together.
         *
         * So, to simplify things, we won't be using curves, but only straight lines.
         * On the other hand, racking up the number of slashlines is relatively easy,
         * so we cut this into tiny little slices, so that the straight lines aren't that
         * big a deal
         */
        $steps = 100;
        $distance = $p->deltaY(12,5)/($steps+1);
        $left = $p->x('feTop');
        $right = $p->x('slArm');
        $bottom = $p->y(5);
        for($i=1; $i<=$steps; $i++) {

            // Slash line coordinates
            $lpid = "leftStep$i";
            $rpid = "rightStep$i";
            $p->newPoint($lpid, $left, $bottom - $i*$distance);
            $p->newPoint($rpid, $right, $bottom - $i*$distance);

            // Find left intersection points
            if($p->y($lpid) < $p->y('feTop')) { // In neck curve
                $p->curveCrossesY(8,20,21,9,$p->y($lpid),'.isect');
                // Overwrite point
                $p->clonePoint('.isect1', $lpid);
            } else { 
                // Store id of last point to fall on center front
                $this->setValue('cfTipPoint', $lpid);
                $this->setValue('cfTipPointNext', 'leftStep'.($i+1));
            }
            
            // Find right intersection points
            if($p->y($rpid) > $p->y(14)) { // In first curve of the armhole
                $p->curveCrossesY(14, '14CpRight', 'slArmCpLeft','slArm',$p->y($rpid),'.isect');
                $p->clonePoint('.isect1', $rpid); // Overwrite point
            }
            else if($p->y($rpid) > $p->y(10)) { // In second curve of the armhole
                $p->curveCrossesY(10,18,15,14,$p->y($rpid),'.isect');
                $p->clonePoint('.isect1', $rpid); // Overwrite point
            } else { // In third and final curve of the armhole
                $p->curveCrossesY(12,19,17,10,$p->y($rpid),'.isect');
                $p->clonePoint('.isect1', $rpid); // Overwrite point
            }
        }

        // Add start and end line
        $p->clonePoint('slArm', 'rightStep0');
        $p->newPoint('leftStep0', $p->x('feTop'), $p->y('rightStep0'));
        $p->clonePoint(12, 'rightStep'.($steps+1));
        $p->clonePoint(8, 'leftStep'.($steps+1));

        // Figure out how much we need to rotate
        $distance = $model->m('chestCircumference') * $this->o('chestShaping');
        $p->newPoint('.helper', $p->x('feTop'), $p->y(10) - $distance);
        $angle = -1*(360-$p->angle('.helper', 10))/$steps;

        // Rotate points in second loop, because we need them all before we can do this
        $steps++;
        $pathDown = [];
        for($i=1; $i<=$steps; $i++) {
            for($j=$i; $j<=$steps; $j++) {
                $rotateThese[] = "leftStep$j";
                $rotateThese[] = "rightStep$j";
            }
            foreach($rotateThese as $pid) $p->addPoint($pid, $p->rotate($pid, "rightStep$i", $angle));
            unset($rotateThese);
            $pathDown[] = "L rightStep$i ";
        }
        // Clone endpoints to avoid breaking things when the nr of steps change
        $p->clonePoint('rightStep'.$steps, 'shoulderLineRight');
        $p->clonePoint('leftStep'.$steps, 'shoulderLineLeft');

        // Now reconstruct the armhole in a proper curve
        $p->clonePoint('shoulderLineRight', 12);
        $p->addPoint(19, $p->shift(12, $p->angle('shoulderLineLeft','shoulderLineRight')-90, 15));
        $p->addPoint(10, $p->shift(10, 0, 3));
        $p->addPoint(17, $p->shift(17, 0, 3));
        $p->addPoint(18, $p->shift(18, 0, 3));
        $p->newPath('test', 'M 12 C 19 17 10 C 18 15 14', ['class' => 'debug']);


        // Lapel break point and roll line
        $p->newPoint('breakPoint', $p->x('feBottom'), $p->y(3) - ($p->distance(2,3) * $this->o('lapelStart')));
        $p->addPoint('cutawayPoint', $p->shift('breakPoint',-90,$p->distance(2,3) * $this->o('lapelStart') + 10/$this->o('lapelStart')));
        $p->newPath('sdfsdss', 'M breakPoint L cutawayPoint', ['class' => 'debug']);
        $p->addPoint('shoulderRoll', $p->shiftOutwards('shoulderLineRight','shoulderLineLeft', $this->o('rollLineCollarHeight')));
        $p->addPoint('shoulderRollCb', $p->shiftOutwards('breakPoint','shoulderRoll', $this->v('backCollarLength')));
        $p->addPoint('collarCbHelp', $p->shift('shoulderRollCb', $p->angle('shoulderRoll','shoulderRollCb')-90, $this->o('rollLineCollarHeight')));
        $p->addPoint('collarCbBottom', $p->shift('collarCbHelp', $p->angle('shoulderRoll','collarCbHelp')-90, $this->o('rollLineCollarHeight')));
        $p->addPoint('collarCbTop',    $p->shift('collarCbHelp', $p->angle('shoulderRoll','collarCbHelp')+90,  $this->v('collarHeight')*2 - $this->o('rollLineCollarHeight')));
        
        // Notch (prevent it from getting too deep)
        $maxNotch = $p->distance($this->v('cfTipPoint'), $this->v('cfTipPointNext'));
        if($this->o('collarNotchDepth') > $maxNotch) $this->setValue('collarNotchDepth', $maxNotch);
        else $this->setValue('collarNotchDepth', $this->o('collarNotchDepth'));
        $p->addPoint('notchPoint', $p->shiftTowards($this->v('cfTipPoint'), $this->v('cfTipPointNext'), $this->v('collarNotchDepth')));
        $p->addPoint('notchTip', $p->rotate($this->v('cfTipPoint'), 'notchPoint', -1 * $this->o('collarNotchAngle')));
        $p->addPoint('notchTip', $p->shiftTowards('notchPoint', 'notchTip', $this->v('collarNotchDepth') * $this->o('collarNotchReturn')));
        $p->addPoint('notchTipCp', $p->shift('notchTip', $p->angle('notchPoint','notchTip')-90, $p->distance('notchTip', 'collarCbTop')/4));
        $p->addPoint('collarCbTopCp', $p->shift('collarCbTop', $p->angle('collarCbBottom','collarCbTop')+90, $p->distance('notchTip', 'collarCbTop')/4));

        // Redraw front neck line
        $p->clonePoint($this->v('cfTipPoint'), 'cfRealTop');
        $p->clonePoint('leftStep1', 'breakPointCp');
        $p->addPoint('.cpHelper1', $p->rotate('collarCbHelp','collarCbBottom',90));
        $p->addPoint('.cpHelper2', $p->beamsCross('cfRealTop', 'notchPoint', 'collarCbBottom', '.cpHelper1'));
        $p->addPoint('notchPointCp', $p->shiftFractionTowards('notchPoint', '.cpHelper2', 0.75));
        $p->addPoint('shoulderLineRealLeft', $p->beamsCross('shoulderLineRight', 'shoulderLineLeft', 'collarCbBottom', '.cpHelper1'));
        $p->addPoint('shoulderLineRealLeftCp', $p->shiftFractionTowards('shoulderLineRealLeft', '.cpHelper2', 0.75));

        // Now adapt to fit the back neck curve length
        if($p->distance('shoulderLineRealLeft', 'collarCbBottom') != $this->v('backCollarLength')) {
            $delta = $p->distance('shoulderLineRealLeft', 'collarCbBottom') - $this->v('backCollarLength');
            $angle = $p->angle('collarCbBottom', 'shoulderLineRealLeft');
            $shiftThese = ['collarCbBottom','collarCbTop', 'collarCbTopCp'];
            foreach($shiftThese as $pid) $p->addPoint($pid, $p->shift($pid, $angle, $delta));
        }
    
        // Seperation between front and collar
        $p->addPoint('shiftedNotchPoint', $p->shift('notchPoint',0,10));
        $p->addPoint('shiftedNotchPoint', $p->rotate('shiftedNotchPoint','notchPoint',30)); 
        $p->addPoint('foldNotchHeight', $p->beamsCross('breakPoint', 'shoulderRoll', 'notchPoint', 'shiftedNotchPoint'));
        $p->addPoint('collarCorner', $p->shift('foldNotchHeight', 0, $p->distance('shoulderRoll', 'shoulderLineRealLeft')/2));
        $p->addPoint('rollLineTop', $p->beamsCross('breakPoint', 'shoulderRoll', 'notchPoint', 'collarCorner'));


        // Store pocket info for side panel
        $p->curveCrossesY('waistBackSide','waistBackSideCpBottom','hipsBackSideCpTop','hipsBackSide', $p->y('fpTopRight'), 'fpHelpTop');
        $this->setValue('fpTopWidth', $p->distance('fpHelpTop1', 'fpTopRight'));
        $this->setValue('fpBottomWidth', $p->deltaX('hipsBackSide', 'fpBottomRight'));
        $this->setValue('fpHeight', $p->deltaY('fpTopRight', 'fpBottomRight'));
        $this->setValue('fpStartY', $p->y('fpTopRight'));
        // Move top and bottom right to edge
        $p->clonePoint('fpHelpTop1', 'fpTopRight');
        $p->newPoint('fpBottomRight', $p->x('hipsBackSide'), $p->y('fpBottomLeft'));

        // Add extra hem allowance (3cm)
        $p->addPoint('frontSideHemEdge', $p->shift('frontSideHem', -90, $this->o('sa')*3));
        $p->addPoint('cfHemEdge', $p->shift('cfHem', -90, $this->o('sa')*3));
        $p->addPoint('feBottomHemEdge', $p->shift('feBottom', -90, $this->o('sa')*3));

        // Round the front at hem
        $p->addPoint('roundTop', $p->shiftFractionTowards('cutawayPoint','feBottomHemEdge', $this->o('frontCutawayStart')));
        $p->addPoint('roundTop', $p->rotate('roundTop', 'cutawayPoint', $this->o('frontCutawayAngle')));
        $p->addPoint('roundTopCp', $p->shiftFractionTowards('feTop','feBottom', 0.95));
        $p->newPoint('roundTopCp', $p->x('roundTop'), $p->y('roundTopCp'));
        $p->addPoint('roundTopCp', $p->rotate('roundTopCp', 'roundTop', $this->o('frontCutawayAngle')));
        $p->addPoint('roundRight', $p->shiftFractionTowards('cfHem','frontSideHem', 0.3));
        $p->addPoint('roundRightCp', $p->shiftFractionTowards('cfHem','frontSideHem', 0.05));

        // Smooth out curve
        $p->addPoint('roundTop',$p->shift('roundTop',180,$p->deltaX('cutawayPoint','roundTop')/2));
        $p->addPoint('roundTopCpTop', $p->beamsCross('breakPoint','cutawayPoint','roundTop','roundTopCp'));
        $p->addPoint('cutawayPointCp', $p->shiftFractionTowards('cutawayPoint','roundTopCpTop',0.5));

        // Facing/lining boundary
        $p->addPoint('facingTop', $p->shiftFractionTowards('shoulderLineRealLeft','shoulderLineRight',0.2));    

        // Inner pocket facing extension (ipfe)
        $ipfeRadius = 20;
        $p->addPoint('.help1', $p->shift('ipMidLeft', 90, $ipfeRadius));
        $p->addPoint('.help2', $p->shift('ipMidRight', 90, $ipfeRadius));
        $p->addPoint('ipfeTopLeft', $p->beamsCross('facingTop','roundRight','.help1','.help2'));
        $p->addPoint('.help1', $p->shift('ipMidLeft', -90, $ipfeRadius));
        $p->addPoint('.help2', $p->shift('ipMidRight', -90, $ipfeRadius));
        $p->addPoint('ipfeBotLeft', $p->beamsCross('facingTop','roundRight','.help1','.help2'));
        $p->newPoint('ipfeTopRight', $p->x('ipMidRight')+$ipfeRadius, $p->y('ipfeTopLeft'));
        $p->newPoint('ipfeBotRight', $p->x('ipfeTopRight'), $p->y('ipfeBotLeft'));
        $p->addPoint('ipfeTopRightTop', $p->shift('ipfeTopRight', 180, $ipfeRadius));
        $p->addPoint('ipfeTopRightBot', $p->shift('ipfeTopRight', -90, $ipfeRadius));
        $p->addPoint('ipfeTopRightCpTop', $p->shift('ipfeTopRightTop', 0, BezierToolbox::bezierCircle($ipfeRadius)));
        $p->addPoint('ipfeTopRightCpBot', $p->shift('ipfeTopRightBot', 90, BezierToolbox::bezierCircle($ipfeRadius)));
        $p->addPoint('ipfeBotRightBot', $p->shift('ipfeBotRight', 180, $ipfeRadius));
        $p->addPoint('ipfeBotRightTop', $p->shift('ipfeBotRight', 90, $ipfeRadius));
        $p->addPoint('ipfeBotRightCpTop', $p->shift('ipfeBotRightTop', -90, BezierToolbox::bezierCircle($ipfeRadius)));
        $p->addPoint('ipfeBotRightCpBot', $p->shift('ipfeBotRightBot', 0, BezierToolbox::bezierCircle($ipfeRadius)));
        $p->addPoint('ipfeTopLeftTop', $p->shiftTowards('ipfeTopLeft','facingTop', $ipfeRadius));
        $p->addPoint('ipfeTopLeftBot', $p->shiftTowards('ipfeTopLeft','ipfeTopRight', $ipfeRadius));
        $p->addPoint('ipfeTopLeftTopCp', $p->shiftTowards('ipfeTopLeftTop', 'ipfeTopLeft', BezierToolbox::bezierCircle($ipfeRadius)));
        $p->addPoint('ipfeTopLeftBotCp', $p->shift('ipfeTopLeftBot', 180, BezierToolbox::bezierCircle($ipfeRadius)));
        $p->addPoint('ipfeBotLeftBot', $p->shiftTowards('ipfeBotLeft','roundRight', $ipfeRadius));
        $p->addPoint('ipfeBotLeftTop', $p->shift('ipfeBotLeft', 0, $ipfeRadius));
        $p->addPoint('ipfeBotLeftBotCp', $p->shiftTowards('ipfeBotLeftBot','ipfeBotLeft', BezierToolbox::bezierCircle($ipfeRadius)));
        $p->addPoint('ipfeBotLeftTopCp', $p->shift('ipfeBotLeftTop', 180, BezierToolbox::bezierCircle($ipfeRadius)));
        
        // Paths
        $p->newPath('front', '
            M breakPoint 
            C breakPointCp cfRealTop cfRealTop 
            L notchPoint 
            L collarCorner
            L shoulderLineRealLeft 
            L shoulderLineRight
            C 19 17 10
            C 18 15 14
            C 14CpRight slArmCpLeft slArm
            C slArm waistBackSideCpTop waistBackSide
            C waistBackSideCpBottom hipsBackSideCpTop hipsBackSide
            L frontSideHem
            L roundRight
            C roundRightCp roundTopCp roundTop
            C roundTopCpTop cutawayPointCp cutawayPoint
            L breakPoint
            z
            M frontDartBottom 
            C frontDartBottom frontDartRightCpBottom frontDartRight
            C frontDartRightCpTop frontDartTop frontDartTop
            C frontDartTop frontDartLeftCpTop frontDartLeft
            C frontDartLeftCpBottom frontDartBottom frontDartBottom
            z
             
            ', ['class' => 'fabric']);

        $p->newPath('cf', 'M 9 L cfHem', ['class' => 'help']);
        $p->newPath('chestPocket', ' M cpBottomLeft L cpTopLeft L cpTopRight L cpBottomRight L cpBottomLeft z', ['class' => 'help']);
        $p->newPath('rollline', 'M breakPoint L rollLineTop', ['class' => 'help']);
        $p->newPath('facing', 'M facingTop 
            L ipfeTopLeftTop 
            C ipfeTopLeftTopCp ipfeTopLeftBotCp ipfeTopLeftBot
            L ipfeTopRightTop
            C ipfeTopRightCpTop ipfeTopRightCpBot ipfeTopRightBot
            C ipfeBotRightCpTop ipfeBotRightCpBot ipfeBotRightBot
            L ipfeBotLeftTop
            C ipfeBotLeftTopCp ipfeBotLeftBotCp ipfeBotLeftBot 
            L roundRight'
            , ['class' => 'fabric']);
        $p->newPath('lining1', 'M facingTop L ipfeTopLeftTop M ipfeBotLeftBot L roundRight', ['class' => 'lining', 'stroke-dasharray' => '10,10']);
        $p->newPath('lining2', 'M ipfeTopLeftTop L ipfeBotLeftBot', ['class' => 'lining']);
        $p->newPath('innerPocket', 'M ipTopLeft L ipTopRight L ipBotRight L ipBotLeft z M ipMidLeft L ipMidRight', ['class' => 'help']); 

        // 3cm extra hem allowance
        $p->addPoint('roundedHem', $p->shift('roundRight',-90, $this->o('sa')*3));

        // Mark path for sample service
        $p->clonePoint('feBottomHemEdge', 'gridAnchor');
        $p->paths['front']->setSample(true);
        $p->paths['chestPocket']->setSample(true);
        $p->paths['frontPocket']->setSample(true);
        $p->paths['rollline']->setSample(true);

        // Store lenght of the sleeve cap to the pitch point notch
        $this->setValue('frontSleevecapToNotch', $p->curveLen('shoulderLineRight', 19, 17, 10)); 
    }

    /**
     * Drafts the side
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function draftSide($model)
    {
        $this->clonePoints('frontBlock', 'side');

        /** @var \Freesewing\Part $p */
        $p = $this->parts['side'];
        
        // Front pocket
        $p->curveCrossesY('waistSideBack','waistSideBackCpBottom','hipsSideBackCpTop','hipsSideBack',$this->v('fpStartY'), 'fpHelp');
        $p->clonePoint('fpHelp1','fpTopLeft');
        $p->addPoint('fpTopRight', $p->shift('fpTopLeft', 0, $this->v('fpTopWidth')));
        $p->newPoint('fpBottomLeft', $p->x('sideFrontHem'), $p->y('fpTopLeft') + $this->v('fpHeight'));
        $p->addPoint('fpBottomRight', $p->shift('fpBottomLeft', 0, $this->v('fpBottomWidth')));

        // Add extra hem allowance (3cm)
        $p->addPoint('hemEdgeBackSide', $p->shift('sideHemSideBack', -90, $this->o('sa')*3));
        $p->addPoint('hemEdgeFrontSide', $p->shift('sideFrontHem', -90, $this->o('sa')*3));
        
        // Back vent
        if($this->o('backVent') == 2) {
            // Vent tip
            $p->addPoint('ventTip', $p->shiftAlong('sideWaistSideBack','sideWaistSideBackCpBottom','sideHipsSideBackCpTop','sideHipsSideBack', $this->v('waistToBackVent')));
            // Vent facing
            $p->splitCurve('sideWaistSideBack','sideWaistSideBackCpBottom','sideHipsSideBackCpTop','sideHipsSideBack','ventTip','ventSplit');
            $p->addPoint('ventFacingBase', $p->shiftAlong('ventTip','ventSplit7','ventSplit6','sideHipsSideBack', 15));
            $p->splitCurve('sideWaistSideBack','sideWaistSideBackCpBottom','sideHipsSideBackCpTop','sideHipsSideBack','ventFacingBase','ventFacingSplit');
            $p->offsetPathString('ventFacing', 'M ventFacingBase C ventFacingSplit7 ventFacingSplit6 sideHipsSideBack', 40);
            $p->addPoint('.help-ventFacingBottomRight', $p->shiftTowards('sideHemSideBack', 'sideFrontHem', 40));
            $p->newPoint('ventFacingBottomRight', $p->x('sideHemSideBack')+40, $p->y('.help-ventFacingBottomRight'));
            $p->newPath('tmp', 'M sideHemSideBack L sideHipsSideBack C ventSplit6 ventSplit7 ventTip', ['class' => 'hint']);
            $path2 = 'C ventSplit2 ventSplit3 ventTip
                L ventFacing-startPoint
                C ventFacing-cp1--ventFacingBase.ventFacingSplit7.ventFacingSplit6.sideHipsSideBack ventFacing-cp2--ventFacingBase.ventFacingSplit7.ventFacingSplit6.sideHipsSideBack ventFacing-curve-sideHipsSideBackTOventFacingBase
                L ventFacingBottomRight
                L sideHemSideBack';
        } else {
            $path2 = 'C sideWaistSideBackCpBottom sideHipsSideBackCpTop sideHipsSideBack L sideHemSideBack';
        }


        $p->newPath('pocket', 'M fpTopLeft L fpTopRight L fpBottomRight L fpBottomLeft', ['class' => 'help']);

        $p->newPath('outline','
            M sideFrontHem
            L hipsSideBack
            C hipsSideBackCpTop waistSideBackCpBottom waistSideBack
            C waistSideBackCpTop slArm slArm
            C slArmCpRight 5CpLeft 5
            C side13 side16 side14
            C sideSlArmCpBottom sideWaistSideBackCpTop sideWaistSideBack
            '.$path2.'
            L sideFrontHem
            z
            ', ['class' => 'fabric']);
        
        // Mark path for sample service
        $p->clonePoint('hemEdgeFrontSide','gridAnchor');
        $p->paths['outline']->setSample(true);

    }


    /**
     * Drafts the back
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function draftBack($model)
    {
        $this->clonePoints('backBlock', 'back');

        /** @var \Freesewing\Part $p */
        $p = $this->parts['back'];

        // Back vent
        if($this->o('backVent') == 1) { // Single back vent
            // Vent tip
            $p->curveCrossesY('hipsCenter','hipsCenterCpTop','waistCenterCpBottom','waistCenter',$p->y('hipsCenter') - $p->deltaY('waistCenter','hipsCenter') * $this->o('backVentLength'), 'vent');
            $p->clonePoint('vent1', 'ventTip');
            // Vent facing
            $p->splitCurve('hipsCenter','hipsCenterCpTop','waistCenterCpBottom','waistCenter','ventTip','ventSplit');
            $p->addPoint('ventFacingBase', $p->shiftAlong('ventTip','ventSplit3','ventSplit2','hipsCenter', 15));
            $p->splitCurve('hipsCenter','hipsCenterCpTop','waistCenterCpBottom','waistCenter','ventFacingBase','ventFacingSplit');
            $p->offsetPathString('ventFacing', 'M hipsCenter C ventFacingSplit2 ventFacingSplit3 ventFacingBase', 40);
            $p->addPoint('ventFacingBottomLeft', $p->shift('hemCenter', 180, 40));

            $p->newPath('tmp', 'M hemCenter L hipsCenter C hipsCenterCpTop waistCenterCpBottom waistCenter', ['class' => 'hint']);
            
            $path1 = 'L ventFacingBottomLeft 
                    L ventFacing-startPoint 
                    C ventFacing-cp1--hipsCenter.ventFacingSplit2.ventFacingSplit3.ventFacingBase ventFacing-cp2--hipsCenter.ventFacingSplit2.ventFacingSplit3.ventFacingBase ventFacing-endPoint 
                    L ventTip
                    C ventSplit7 ventSplit6 waistCenter';
            $path2 = 'C waistBackSideCpBottom hipsBackSideCpTop hipsBackSide L hemBackSide';
        } else if($this->o('backVent') == 2) { // Double back vent
            // Vent tip
            $p->curveCrossesY('waistBackSide','waistBackSideCpBottom','hipsBackSideCpTop','hipsBackSide',$p->y('hipsCenter') - $p->deltaY('waistCenter','hipsCenter') * $this->o('backVentLength'), 'vent');
            $p->clonePoint('vent1', 'ventTip');
            // Vent facing
            $p->splitCurve('waistBackSide','waistBackSideCpBottom','hipsBackSideCpTop','hipsBackSide','ventTip','ventSplit');
            $p->splitCurve('hipsBackSide','hipsBackSideCpTop','waistBackSideCpBottom','waistBackSide','ventTip','ventSplit');
            $p->addPoint('ventFacingBase', $p->shiftAlong('ventTip','ventSplit3','ventSplit2','hipsCenter', 15));
            $p->splitCurve('waistBackSide','waistBackSideCpBottom','hipsBackSideCpTop','hipsBackSide','ventFacingBase','ventFacingSplit');
            $p->offsetPathString('ventFacing', 'M hipsBackSide C ventFacingSplit6 ventFacingSplit7 ventFacingBase', -40);
            $p->addPoint('ventFacingBottomRight', $p->shift('hemBackSide', 0, 40));
            $p->newPath('tmp', 'M hemBackSide L hipsBackSide C ventSplit6 ventSplit7 ventTip', ['class' => 'hint']);
            
            $path2 = 'C ventSplit6 ventSplit7 ventTip
                L ventFacing-endPoint
                C ventFacing-cp2--hipsBackSide.ventFacingSplit6.ventFacingSplit7.ventFacingBase ventFacing-cp1--hipsBackSide.ventFacingSplit6.ventFacingSplit7.ventFacingBase ventFacing-startPoint
                L ventFacingBottomRight';
            $path1 = 'L hipsCenter C hipsCenterCpTop waistCenterCpBottom waistCenter';

            // Store distance to vent start for side
            $this->setValue('waistToBackVent', $p->curveLen('waistBackSide','ventSplit6','ventSplit7','ventTip'));
        } else {
            $path1 = 'L hipsCenter C hipsCenterCpTop waistCenterCpBottom waistCenter';
            $path2 = 'C waistBackSideCpBottom hipsBackSideCpTop hipsBackSide L hemBackSide';
        }

        // Add extra hem allowance (3*SA)
        $p->addPoint('hemEdgeBackSide', $p->shift('hemBackSide', -90, $this->o('sa')*3));
        $p->addPoint('hemEdgeCenter', $p->shift('hemCenter', -90, $this->o('sa')*3));
        if($this->o('backVent') == 1) $p->addPoint('hemEdgeVent', $p->shift('ventFacingBottomLeft',-90,$this->o('sa')*3));

        $p->newPath('outline','
            M centerBackNeck 
            C centerBackNeck 20 8
            L 12
            C 19 17 10
            C 18 15 14
            C slArmCpBottom waistBackSideCpTop waistBackSide
            '.$path2.'
            L hemCenter
            '.$path1.'
            C waistCenterCpTop chestCenterCpBottom chestCenter
            C chestCenterCpTop centerBackNeck centerBackNeck
            z
            ', ['class' => 'fabric']);

        // Mark path for sample service
        $p->paths['outline']->setSample(true);

        // Store lenght of the sleeve cap to the pitch point notch
        $this->setValue('backSleevecapToNotch', $p->curveLen(12, 19, 17, 10)); 
    }


    /**
     * Drafts the collar
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function draftCollar($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['collar'];

        /** @var \Freesewing\Part $p */
        $front = $this->parts['front'];

        // Cloning all points from front seems like overkill
        $cloneThese = [
            'collarCbBottom',
            'collarCbTop',
            'collarCbTopCp',
            'collarCorner',
            'notchPoint',
            'notchTip',
            'notchTipCp',
            'shoulderLineRealLeft',
        ];
        foreach($cloneThese as $pid) $p->newPoint($pid, $front->x($pid), $front->y($pid));
        
        // Rotate entire part
        $angle = $front->angle('collarCbBottom', 'collarCbTop');
        foreach($cloneThese as $pid) $p->addPoint($pid, $p->rotate($pid, 'collarCbTop', $angle*-1+90));

        // Tweak bottom shape
        $p->addPoint('shoulderLineRealLeft', $p->shiftFractionTowards('collarCbBottom','shoulderLineRealLeft', 1.4));

        // Bend the collar
        $angle = 5;
        $p->addPoint('bottomLeft', $p->rotate('collarCorner', 'collarCbBottom', $angle));
        $p->addPoint('helper', $p->shiftAlong('bottomLeft','bottomLeft','shoulderLineRealLeft','collarCbBottom', 1));
        $delta = $this->bendedCollarDelta();
        $tweaks = 0;
        while(abs($delta) > 1 && $tweaks < 50) {
            $p->addPoint('bottomLeft', $p->shiftTowards('helper','bottomLeft', 1-$delta));
            $tweaks++;
            $delta = $this->bendedCollarDelta();
        }
        $this->msg("After $tweaks attemps, the collar length is ".round($delta).'mm off.');
        $rotateThese = ['notchPoint','notchTip','notchTipCp'];
        foreach($rotateThese as $pid) $p->addPoint($pid, $p->rotate($pid, 'collarCbBottom', $angle));

        // Tweak top shape
        $p->newPoint('collarCbTopCp', $p->x('shoulderLineRealLeft'), $p->y('collarCbTopCp'));
        $p->addPoint('collarCbTopCp', $p->shiftOutwards('collarCbTop', 'collarCbTopCp', $p->distance('collarCbTop', 'collarCbTopCp')/10));
        $p->addPoint('notchTipCp', $p->shiftFractionTowards('notchTip','collarCbTop', 0.25));

        // Undercollar line
        $p->addPoint('ucTop', $p->shift('collarCbBottom', 90, 20));
        $p->addPoint('ucTopCpLeft', $p->shift('shoulderLineRealLeft', 90, 20));
        $p->addPoint('ucTopCpRight', $p->flipX('ucTopCpLeft', $p->x('ucTop')));
        $p->addPoint('ucTipLeft', $p->shiftTowards('bottomLeft', 'notchPoint', 20));
        $p->addPoint('ucTipRight', $p->flipX('ucTipLeft', $p->x('ucTop')));

        // End undercollar before end of collar
        $p->addPoint('ucBottomLeft', $p->shiftAlong('bottomLeft','bottomLeft','shoulderLineRealLeft','collarCbBottom', $p->distance('bottomLeft','collarCbBottom')/5));
        // Split curve
        $p->splitCurve('bottomLeft','bottomLeft','shoulderLineRealLeft','collarCbBottom','ucBottomLeft','ucBottomCurve');

        // Mirror what we need on the other side
        $mirrorThese = [
            'collarCbTopCp',
            'notchTipCp',
            'notchTip',
            'notchPoint',
            'collarCorner',
            'shoulderLineRealLeft',
            'bottomLeft',
            'ucBottomLeft',
            'ucBottomCurve3',
            'ucBottomCurve6',
            'ucBottomCurve7',
        ];
        foreach($mirrorThese as $pid) $p->addPoint("m.$pid", $p->flipX($pid, $p->x('collarCbTop')));

        $p->newPath('outline', '
            M notchPoint 
            L bottomLeft 
            C bottomLeft ucBottomCurve3 ucBottomLeft
            C ucBottomLeft ucTopCpLeft ucTop
            C ucTopCpRight m.ucBottomLeft m.ucBottomLeft            
            C m.ucBottomCurve3 m.bottomLeft m.bottomLeft
            L m.notchPoint 
            L m.notchTip
            C m.notchTipCp m.collarCbTopCp collarCbTop
            C collarCbTopCp notchTipCp notchTip
            L notchPoint
            z
            ', ['class' => 'fabric']);
        
        // Mark path for sample service
        $p->clonePoint('ucTop','gridAnchor');
        $p->paths['outline']->setSample(true);
    }
    
    /** 
     * Checks the difference in length between the original straight collar and bended collar
     */
    protected function bendedCollarDelta() 
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['collar'];

        $straightLen = $p->distance('collarCorner','shoulderLineRealLeft') + $p->distance('shoulderLineRealLeft','collarCbBottom');
        $bendedLen = $p->curveLen('collarCbBottom','shoulderLineRealLeft','bottomLeft','bottomLeft');

        return $bendedLen - $straightLen;
    }

    /**
     * Drafts the collar stand
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function draftCollarstand($model)
    {
        $this->clonePoints('collar', 'collarstand');

        /** @var \Freesewing\Part $p */
        $p = $this->parts['collarstand'];

        $p->newPath('outline', '
            M ucBottomLeft 
            C ucBottomLeft ucTopCpLeft ucTop
            C ucTopCpRight m.ucBottomLeft m.ucBottomLeft
            C m.ucBottomCurve7 m.ucBottomCurve6 collarCbBottom
            C ucBottomCurve6 ucBottomCurve7 ucBottomLeft
            z
            ', ['class' => 'fabric']);
        
        // Mark path for sample service
        $p->clonePoint('collarCbBottom','gridAnchor');
        $p->paths['outline']->setSample(true);
    }

    /**
     * Drafts the undercollar
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function draftUndercollar($model)
    {
        $this->clonePoints('collar', 'undercollar');

        /** @var \Freesewing\Part $p */
        $p = $this->parts['undercollar'];

        $p->newPath('outline', '
            M notchPoint 
            L bottomLeft
            C bottomLeft shoulderLineRealLeft collarCbBottom
            C m.shoulderLineRealLeft m.bottomLeft m.bottomLeft
            L m.notchPoint 
            L m.notchTip
            C m.notchTipCp m.collarCbTopCp collarCbTop
            C collarCbTopCp notchTipCp notchTip
            L notchPoint
            z
            ', ['class' => 'various']);
        $p->newPath('undercollarLine', '
            M ucBottomLeft 
            C ucBottomLeft ucTopCpLeft ucTop
            C ucTopCpRight m.ucBottomLeft m.ucBottomLeft
            ', ['class' => 'hint']);
        
        // Mark path for sample service
        $p->clonePoint('collarCbBottom','gridAnchor');
        $p->paths['outline']->setSample(true);
    }

    /**
     * Drafts the topsleeve
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function draftTopsleeve($model)
    {
        $this->clonePoints('topsleeveBlock', 'topsleeve');
        
        /** @var \Freesewing\Part $p */
        $p = $this->parts['topsleeve'];

        // Vent 
        $p->addPoint('ventBottomRight', $p->shiftOutwards('topsleeveWristLeft','topsleeveWristRight',$this->v('sleeveVentWidth')));
        $p->addPoint('ventTopLeft', $p->shiftTowards('topsleeveWristRight','elbowRight', $this->v('sleeveVentLength')));
        $p->addPoint('ventTopRight', $p->shiftTowards('topsleeveWristRight','elbowRight', $this->v('sleeveVentLength')-$this->v('sleeveVentWidth')));
        $p->addPoint('ventTopRight', $p->rotate('ventTopRight','ventTopLeft',90));
        $p->addPoint('ventTopRight', $p->shiftTowards('ventTopRight','ventBottomRight',$this->v('sleeveVentWidth')/2));

        // Paths
        $p->newPath('outline', 'M elbowRight C elbowRightCpTop topsleeveRightEdgeCpBottom topsleeveRightEdge C topsleeveRightEdgeCpTop backPitchPoint backPitchPoint C backPitchPoint sleeveTopCpRight sleeveTop C sleeveTopCpLeft frontPitchPointCpTop frontPitchPoint C frontPitchPointCpBottom topsleeveLeftEdgeCpRight topsleeveLeftEdge C topsleeveLeftEdge topsleeveElbowLeftCpTop topsleeveElbowLeft L topsleeveWristLeft L ventBottomRight L ventTopRight L ventTopLeft L elbowRight z', ['class' => 'fabric']);
        $p->newPath('ventHint', 'M ventTopLeft L topsleeveWristRight', ['class' => 'help']);

        // Mark path for sample service
        $p->paths['outline']->setSample(true);

        // Store length of the front and back sleevecap for the topsleeve
        $this->setValue('topsleevecapFrontLength', $p->curveLen('sleeveTop','sleeveTopCpLeft','frontPitchPointCpTop','frontPitchPoint') + $p->curveLen('frontPitchPoint','frontPitchPointCpBottom','topsleeveLeftEdge','topsleeveLeftEdge'));
        $this->setValue('topsleevecapBackLength', $p->curveLen('sleeveTop','sleeveTopCpRight','backPitchPoint','backPitchPoint'));

    }

    /**
     * Drafts the undersleeve
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function draftUndersleeve($model)
    {
        $this->clonePoints('undersleeveBlock', 'undersleeve');
        
        /** @var \Freesewing\Part $p */
        $p = $this->parts['undersleeve'];

        // Vent 
        $p->addPoint('ventBottomRight', $p->shiftOutwards('undersleeveWristLeft','undersleeveWristRight',$this->v('sleeveVentWidth')));
        $p->addPoint('ventTopLeft', $p->shiftTowards('undersleeveWristRight','elbowRight', $this->v('sleeveVentLength')));
        $p->addPoint('ventTopRight', $p->shiftTowards('undersleeveWristRight','elbowRight', $this->v('sleeveVentLength')-$this->v('sleeveVentWidth')));
        $p->addPoint('ventTopRight', $p->rotate('ventTopRight','ventTopLeft',90));
        $p->addPoint('ventTopRight', $p->shiftTowards('ventTopRight','ventBottomRight',$this->v('sleeveVentWidth')/2));

        // Paths
        $p->newPath('undersleeve', 'M undersleeveWristRight L ventBottomRight L ventTopRight L ventTopLeft L elbowRight C elbowRightCpTop undersleeveRightEdgeCpBottom undersleeveRightEdge C undersleeveRightEdgeCpTop undersleeveTip undersleeveTip C undersleeveTipCpBottom undersleeveLeftEdgeCpRight undersleeveLeftEdgeRight L undersleeveLeftEdge C undersleeveLeftEdge undersleeveElbowLeftCpTop undersleeveElbowLeft L undersleeveWristLeft L undersleeveWristRight z', ['class' => 'fabric']);
        $p->newPath('ventHint', 'M ventTopLeft L undersleeveWristRight', ['class' => 'help']);
        
       
        // Mark path for sample service
        $p->paths['undersleeve']->setSample(true);

    }

    /**
     * Drafts the pocket
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function draftPocket($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['pocket'];

        $w = $model->m('chestCircumference') * $this->o('frontPocketWidth');
        $h = $this->v('frontPocketDepth');
        $r = $w/5;
        $br = BezierToolbox::BezierCircle($r);

        $p->newPoint('topLeft', 0, 0);
        $p->newPoint('topRight', $w, 0);
        $p->newPoint('bottomLeft', 0, $h);
        $p->newPoint('bottomRight', $w, $h);
        $p->newPoint('edgeLeft', 0, self::POCKET_FOLDOVER*-1);
        $p->newPoint('edgeRight', $w, self::POCKET_FOLDOVER*-1);


        $p->addPoint('leftArcTop', $p->shift('bottomLeft', 90, $r));
        $p->addPoint('leftArcBot', $p->shift('bottomLeft', 0, $r));
        $p->addPoint('leftArcTopCp', $p->shift('leftArcTop', -90, $br));
        $p->addPoint('leftArcBotCp', $p->shift('leftArcBot', 180, $br));

        $p->addPoint('rightArcTop', $p->shift('bottomRight', 90, $r));
        $p->addPoint('rightArcBot', $p->shift('bottomRight', 180, $r));
        $p->addPoint('rightArcTopCp', $p->shift('rightArcTop', -90, $br));
        $p->addPoint('rightArcBotCp', $p->shift('rightArcBot', 0, $br));

        $p->newPath('outline', 'M edgeLeft L leftArcTop 
            C leftArcTopCp leftArcBotCp leftArcBot
            L rightArcBot 
            C rightArcBotCp rightArcTopCp rightArcTop
            L edgeRight z', ['class' => 'fabric']);
        $p->newPath('foldline', 'M topLeft L topRight', ['class' => 'help']); 

        // Mark path for sample service
        $p->clonePoint('edgeLeft','gridAnchor');
        $p->paths['outline']->setSample(true);
    }

    /**
     * Drafts the chest pocket welt
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function draftChestPocketWelt($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['chestPocketWelt'];
        
        $w = $this->o('chestPocketWidth');
        $h = $this->o('chestPocketWeltSize');
        
        $p->newPoint('midLeft', 0, 0);
        $p->newPoint('midRight', $w, 0);
        $p->newPoint('topLeft', 0, $h*-1);
        $p->newPoint('.help',0, -20);
        $p->addPoint('.help', $p->rotate('.help','midLeft',$this->o('chestPocketAngle')));
        $p->newPoint('topLeft', 0, $h*-1);
        $p->newPoint('topRight', $w, $h*-1);

        //Fix real location of top corners
        $p->addPoint('topLeft', $p->beamsCross('topLeft','topRight','midLeft','.help'));
        $p->addPoint('topRight', $p->shiftTowards('topLeft','topRight', $w));

        // Bottom corners
        $p->newPoint('botLeft', $p->x('topLeft'), $h);
        $p->newPoint('botRight', $p->x('topRight'), $h);

        // Paths
        $p->newPath('outline', 'M botLeft L botRight L midRight L topRight L topLeft L midLeft z', ['class' => 'fabric']);
        $p->newPath('foldline', 'M midLeft L midRight', ['class' => 'fabric help']);

        // Mark path for sample service
        $p->clonePoint('topLeft','gridAnchor');
        $p->paths['outline']->setSample(true);
    }

    /**
     * Drafts the chest pocket bag
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function draftChestPocketBag($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['chestPocketBag'];

        $w = $this->o('chestPocketWidth') + 30;

        $p->newPoint('topLeft', 0, 0);
        $p->newPoint('topRight', $w, 0);
        $p->newPoint('.help1', $w, 10);
        $p->addPoint('.help2', $p->rotate('topRight','topLeft', $this->o('chestPocketAngle')));
        $p->addPoint('topRight', $p->beamsCross('topLeft','.help2','.help1','topRight'));

        $p->newPoint('midTopLeft', 0, 40);
        $p->newPoint('midTopRight', $w, 40);
        $p->newPoint('midBotLeft', 0, 60);
        $p->newPoint('midBotRight', $w, 60);

        $p->newPoint('botLeft', 0, 100);
        $p->newPoint('botRight', $w, 100);
        $p->addPoint('.help3', $p->rotate('botRight','botLeft', $this->o('chestPocketAngle')*-1));
        $p->addPoint('botRight', $p->beamsCross('botLeft','.help3','botRight','topRight'));

        // Paths
        $p->newPath('outline', 'M midTopLeft L topLeft L topRight L midTopRight M midBotRight L botRight L botLeft L midBotLeft', ['class' => 'lining']);
        $p->newPath('hint', 'M midTopLeft L midBotLeft M midTopRight L midBotRight', ['class' => 'lining hint']); 
        
        // Mark path for sample service
        $p->clonePoint('topLeft','gridAnchor');
        $p->paths['outline']->setSample(true);
    }

    /**
     * Drafts the inner pocket welt
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function draftInnerPocketWelt($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['innerPocketWelt'];

        $w = self::INNER_POCKET_WIDTH + 30;
        $h = self::INNER_POCKET_WELT;
        
        $p->newPoint('topLeft', 0, 0);
        $p->newPoint('topRight', $w, 0);
        $p->newPoint('midLeft', 0, $h);
        $p->newPoint('midRight', $w, $h);
        $p->newPoint('botLeft', 0, $h*2);
        $p->newPoint('botRight', $w, $h*2);
        
        // Paths
        $p->newPath('outline', 'M topLeft L topRight L botRight L botLeft z', ['class' => 'lining']);
        $p->newPath('foldline', 'M midLeft L midRight', ['class' => 'fabric help']); 
        
        // Mark path for sample service
        $p->clonePoint('topLeft','gridAnchor');
        $p->paths['outline']->setSample(true);
    }

    /**
     * Drafts the inner pocket bag
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function draftInnerPocketBag($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['innerPocketBag'];

        $w = self::INNER_POCKET_WIDTH + 30;
        $h = self::INNER_POCKET_DEPTH *2 + 15;

        $p->newPoint('topLeft', 0, 0);
        $p->newPoint('topRight', $w, 0);

        $p->newPoint('midTopLeft', 0, 40);
        $p->newPoint('midTopRight', $w, 40);
        $p->newPoint('midBotLeft', 0, 60);
        $p->newPoint('midBotRight', $w, 60);

        $p->newPoint('botLeft', 0, 100);
        $p->newPoint('botRight', $w, 100);

        // Paths
        $p->newPath('outline', 'M midTopLeft L topLeft L topRight L midTopRight M midBotRight L botRight L botLeft L midBotLeft', ['class' => 'lining']);
        $p->newPath('hint', 'M midTopLeft L midBotLeft M midTopRight L midBotRight', ['class' => 'lining hint']); 

        $p->newHeightDimension('botLeft','topLeft',30, $p->unit($h));
        
        // Mark path for sample service
        $p->clonePoint('topLeft','gridAnchor');
        $p->paths['outline']->setSample(true);
    }


    /**
     * Drafts the inner pocket facing extension
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function draftInnerPocketfacingExtension($model)
    {
        $this->clonePoints('front', 'innerPocketFacingExtension');

        /** @var \Freesewing\Part $p */
        $p = $this->parts['innerPocketFacingExtension'];

        $p->addPoint('tabTop', $p->shift('ipfeTopLeftTop', 180, 10));
        $p->addPoint('tabBot', $p->shift('ipfeBotLeftBot', 180, 10));

        $p->addPoint('weltTop', $p->shift('ipTopRight',180, 10));
        $p->addPoint('weltTop', $p->beamsCross('ipfeTopLeftTop','ipfeBotLeftBot','ipTopRight','weltTop'));
        $p->addPoint('weltMid', $p->shift('ipMidRight',180, 10));
        $p->addPoint('weltMid', $p->beamsCross('ipfeTopLeftTop','ipfeBotLeftBot','ipMidRight','weltMid'));
        $p->addPoint('weltBot', $p->shift('ipBotRight',180, 10));
        $p->addPoint('weltBot', $p->beamsCross('ipfeTopLeftTop','ipfeBotLeftBot','ipBotRight','weltBot'));

        
        $p->newPath('outline', 'M ipfeTopLeftTop 
            C ipfeTopLeftTopCp ipfeTopLeftBotCp ipfeTopLeftBot
            L ipfeTopRightTop
            C ipfeTopRightCpTop ipfeTopRightCpBot ipfeTopRightBot
            C ipfeBotRightCpTop ipfeBotRightCpBot ipfeBotRightBot
            L ipfeBotLeftTop
            C ipfeBotLeftTopCp ipfeBotLeftBotCp ipfeBotLeftBot  z
        ', ['class' => 'fabric']);
        $p->newPath('welts', 'M weltTop L ipTopRight L ipBotRight L weltBot M weltMid L ipMidRight', ['class' => 'hint']);
    }

    /*
       _____ _             _ _
      |  ___(_)_ __   __ _| (_)_______
      | |_  | | '_ \ / _` | | |_  / _ \
      |  _| | | | | | (_| | | |/ /  __/
      |_|   |_|_| |_|\__,_|_|_/___\___|

      Adding titles/logos/seam-allowance/grainline and so on
    */

    /**
     * Finalizes the back
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function finalizeBack($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['back'];

        // Notches
        $p->notch([10,'chestCenter','waistCenter', 'waistBackSide']);
        
        // Sleeve notch for top/under sleeve seam. But in what curve should it go?
        $len1 = $p->curveLen(12,19,17,10);
        $len2 = $len1 + $p->curveLen(10,18,15,'slArm');
        $lenx = $this->v('topsleevecapBackLength') - $this->o('sleevecapEase')/2;
        
        if($lenx == $len1) $p->clonePoint(10, 'sleeveJoint');
        elseif($lenx == $len2) $p->clonePoint('slArm', 'sleeveJoint');
        elseif($lenx < $len1) $p->addPoint('sleeveJoint', $p->shiftAlong(12,19,17,10,$lenx));
        elseif($lenx < $len2) $p->addPoint('sleeveJoint', $p->shiftAlong(10,18,15,'slArm',$lenx-$len1));
        else die('oh boy');
        $p->notch(['sleeveJoint']);

        // Grainline
        $p->newPoint('grainlineTop', $p->x(8), $p->y(8)+10);
        $p->newPoint('grainlineBottom', $p->x('grainlineTop'), $p->y('hemCenter')-10);
        $p->newGrainline('grainlineBottom','grainlineTop', $this->t('Grainline'));

        // Sleevehead SA foldback notch
        $p->addPoint('foldBack', $p->shiftAlong(12,19,17,10,30));
        $p->notch(['foldBack']);

        // Seam allowance
        if($this->o('sa')) {
            $p->offsetPath('sa', 'outline', $this->o('sa'), 1, ['class' => 'fabric sa']);
            // Extra hem allowance
            if($this->o('backVent') == 1) {
                $p->newPoint('sa-line-hemBackSideTOhemCenter', $p->x('hemBackSide')+$this->o('sa'), $p->y('hemBackSide')+$this->o('sa')*3);
                $p->newPoint('sa-line-hemCenterTOventFacingBottomLeft', $p->x('sa-line-hemCenterTOventFacingBottomLeft'), $p->y('sa-line-hemBackSideTOhemCenter'));
                $p->clonePoint('sa-line-hemCenterTOventFacingBottomLeft','sa-line-hemCenterTOhemBackSide');
                $p->newPoint('sa-line-ventFacingBottomLeftTOhemCenter', $p->x('ventFacingBottomLeft')-$this->o('sa'), $p->y('ventFacingBottomLeft')+$this->o('sa')*3);
            } else if($this->o('backVent') == 2) {
                $p->newPoint('sa-line-ventFacingBottomRightTOhemCenter', $p->x('ventFacingBottomRight')+$this->o('sa'), $p->y('ventFacingBottomRight')+$this->o('sa')*3);
                $p->newPoint('sa-line-hemCenterTOventFacingBottomRight', $p->x('hemCenter')-$this->o('sa'), $p->y('hemCenter')+$this->o('sa')*3);
            } else {
                $p->newPoint('sa-line-hemBackSideTOhemCenter', $p->x('hemBackSide')+$this->o('sa'), $p->y('hemBackSide')+$this->o('sa')*3);
                $p->newPoint('sa-line-hemCenterTOhemBackSide', $p->x('hemCenter')-$this->o('sa'), $p->y('hemCenter')+$this->o('sa')*3);
            }
        }

        $p->newPath('waistLine', 'M waistCenter L waistBackSide',['class' => 'help']);

        // Title & logo
        $p->newPoint('titleAnchor', $p->x('slArm')*0.6, $p->y('slArm'));
        $p->addTitle('titleAnchor', 2, $this->t($p->title), '2x '.$this->t('from fabric')."\n".'2x '.$this->t('from lining'));
        $p->addPoint('logoAnchor', $p->shift('titleAnchor', -90, 100));
        $p->newSnippet('logo', 'logo', 'logoAnchor');

        // Notes
        $p->newNote( $p->newId(), 'foldBack', $this->t("Fold back seam allowance\nfrom here to shoulder seam"), 8, 20, 5);
        $p->newNote( $p->newId(), 10, $this->t("Work in sleevecap ease\nfrom this point onwards"), 10, 20, 5);
        $p->newNote( $p->newId(), 'sleeveJoint', $this->t("Topsleeve/Undersleeve joint point"), 8, 20, 5);
        if($this->o('sa')) {
            $p->newNote( $p->newId(), 'hipsBackSideCpTop', $this->t("Standard seam allowance")."\n(".$p->unit($this->o('sa')).')', 8, 20, -3);
            $p->newNote( $p->newId(), 'grainlineBottom', $this->t("Extra hem allowance")."\n(".$p->unit($this->o('sa')*3).')', 11, 20, -23);
        }

        // Text on paths
        $p->newTextOnPath('waistLine', 'M waistCenter L waistBackSide', 'Waistline', false, false);
    }

    /**
     * Finalizes the front
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function finalizeFront($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['front'];

        // Sleeve notch for start sleevecap ease
        $p->notch([10]);

        // Sleeve notch for top/under sleeve seam. But in what curve should it go?
        $len1 = $p->curveLen(12,19,17,10);
        $len2 = $len1 + $p->curveLen(10,18,15,14);
        $len3 = $len2 + $p->curveLen(14,'14CpRight','slArmCpLeft','slArm');
        $lenx = $this->v('topsleevecapFrontLength') - $this->o('sleevecapEase')/2;
        
        if($lenx == $len1) $p->clonePoint(10, 'sleeveJoint');
        elseif($lenx == $len2) $p->clonePoint(14, 'sleeveJoint');
        elseif($lenx == $len3) $p->clonePoint('slArm', 'sleeveJoint');
        elseif($lenx < $len1) $p->addPoint('sleeveJoint', $p->shiftAlong(12,19,17,10,$lenx));
        elseif($lenx < $len2) $p->addPoint('sleeveJoint', $p->shiftAlong(10,18,15,14,$lenx-$len1));
        elseif($lenx < $len3) $p->addPoint('sleeveJoint', $p->shiftAlong(14,'14CpRight','slArmCpLeft','slArm',$lenx-$len2));
        $p->notch(['sleeveJoint']);

        if($this->o('sa')) {
            // Seam allowance
            $p->offsetPathstring('sa1','
                M roundRight
                C roundRightCp roundTopCp roundTop
                L breakPoint
                C breakPointCp cfRealTop cfRealTop 
                L notchPoint
                L collarCorner 
                L shoulderLineRealLeft 
                L 12
                C 19 17 10
                C 18 15 14
                C 14CpRight slArmCpLeft slArm 
                C slArm waistBackSideCpTop waistBackSide 
                C waistBackSideCpBottom hipsBackSideCpTop hipsBackSide 
                L frontSideHem', $this->o('sa'),1, ['class' => 'fabric sa']);
            $p->newPoint('hemRight', $p->x('sa1-endPoint'), $p->y('frontSideHemEdge'));
            $p->newPath('sa2', 'M sa1-startPoint L roundedHem L frontSideHemEdge L hemRight L sa1-endPoint', ['class' => 'fabric sa']);
        }

        $p->newTextOnPath('facing', 'M roundRight L facingTop', 'Facing/Lining boundary, facing side', ['dy' => -3, 'class' => 'fill-fabric'], false);
        $p->newTextOnPath('lining', 'M roundRight L facingTop', 'Facing/Lining boundary, lining side', ['dy' => 6, 'class' => 'fill-lining'], false);
        $p->newPath('waistLine', 'M 3 L waistBackSide', ['class' => 'help']);

        // Text on paths
        $p->newTextOnPath('waistLine', 'M 3 L waistBackSide', $this->t('Waistline'), false, false);
        $p->newTextOnPath('centerFront', 'M cfHem L chestCenterCpTop', $this->t('Center front').' - '.$this->t('Grainline'), false, false);
        $p->newTextOnPath('rollLine', 'M breakPoint L rollLineTop', $this->t('Roll line'), false, false);

        // Title and logo
        $p->addPoint('titleAnchor', $p->shift('frontDartLeftCpTop', 160, 50));
        $p->addTitle('titleAnchor', 1, $this->t($p->title), '2x '.$this->t('from fabric')."\n".$this->t('Lining part').' 2x '.$this->t('from lining')."\n".$this->t('Facing part').' 2x '.$this->t('from fabric'));
        $p->addPoint('logoAnchor', $p->shift('frontDartBottom', -90, 50));
        $p->newSnippet('logo', 'logo', 'logoAnchor');
        
        // Notes
        $p->addPoint('foldBack', $p->shiftAlong('shoulderLineRight',19,17,10,30));
        $p->newNote( $p->newId(), 'foldBack', $this->t("Fold back seam allowance\nfrom here to shoulder seam"), 8, 20, 5);
        $p->newNote( $p->newId(), 10, $this->t("Work in sleevecap ease\nfrom this point onwards"), 10, 20, 5);
        $p->newNote( $p->newId(), 'sleeveJoint', $this->t("Topsleeve/Undersleeve joint point"), 8, 20, 5);
        if($this->o('sa')) {
            $p->newNote( $p->newId(), 'hipsBackSideCpTop', $this->t("Standard seam allowance")."\n(".$p->unit(10).')', 8, 20, -3);
            $p->newNote( $p->newId(), 'frontSideHemEdge', $this->t("Extra hem allowance")."\n(".$p->unit(30).')', 11, 50, 23);
        }

        // Grainline
        $p->addPoint('grainlineTop', $p->shiftFractionTowards('shoulderLineRealLeft','shoulderLineRight', 0.5));
        $p->newPoint('grainlineBottom', $p->x('grainlineTop'), $p->y('hemCenter')-10);
        $p->newGrainline('grainlineBottom','grainlineTop', $this->t('Grainline'));

        // Notches
        $p->notch(['foldBack','waistBackSide',3, 'ipMidRight','ipMidLeft']);

        // Buttons
        $p->newPoint('topButton', $p->x(3), $p->y('breakPoint'));
        $p->newPoint('bottomButton', $p->x('topButton'), $p->y('cutawayPoint'));
        $p->newSnippet('topButton','button-lg','topButton');
        $p->newSnippet('bottomButton','button-lg','bottomButton');

    }

    /**
     * Finalizes the side
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function finalizeSide($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['side'];

        if($this->o('sa')) {
            // Seam allowance
            $p->offsetPath('sa','outline', $this->o('sa'), 1, ['class' => 'fabric sa']);
            // Extra hem allowance
            if($this->o('backVent') == 2) {
                $p->newPoint('sa-line-ventFacingBottomRightTOsideHemSideBack', $p->x('ventFacingBottomRight'), $p->y('ventFacingBottomRight')+$this->o('sa')*3);
                $p->newPoint('sa-line-ventFacingBottomRightTOventFacing-curve-sideHipsSideBackTOventFacingBase', 
                    $p->x('sa-line-ventFacingBottomRightTOventFacing-curve-sideHipsSideBackTOventFacingBase'), 
                    $p->y('sa-line-ventFacingBottomRightTOsideHemSideBack')
                );
                $p->newPoint('intersection-2', 
                    $p->x('sa-line-sideHemSideBackTOsideFrontHem'),
                    $p->y('sa-line-sideHemSideBackTOsideFrontHem')+$this->o('sa')*2
                );
                $p->newPoint('sa-line-sideFrontHemTOsideHemSideBack', 
                    $p->x('sideFrontHem')-$this->o('sa'),
                    $p->y('sideFrontHem')+$this->o('sa')*3
                );
            } else {
                $p->newPoint('sa-line-sideHemSideBackTOsideFrontHem', $p->x('sideHemSideBack')+$this->o('sa'), $p->y('sideHemSideBack')+$this->o('sa')*3);
                $p->newPoint('sa-line-sideFrontHemTOsideHemSideBack', $p->x('sideFrontHem')-$this->o('sa'), $p->y('sideFrontHem')+$this->o('sa')*3);
            }
        }
        $p->newPath('waistLine', 'M sideWaistSideBack L waistSideBack', ['class' => 'help']);
        $p->newTextOnPath('waistLine', 'M waistSideBack L sideWaistSideBack', $this->t('Waistline'), false, false);

        // Grainline
        $p->addPoint('grainlineBottom', $p->shiftFractionTowards('sideFrontHem','sideHemSideBack',0.5));
        $p->addPoint('grainlineBottom', $p->shift('grainlineBottom',90,10));
        $p->newPoint('grainlineTop', $p->x('grainlineBottom'), $p->y(5)+10);
        $p->newGrainline('grainlineBottom', 'grainlineTop', $this->t('Grainline'));

        // Title and logo
        $p->addPoint('titleAnchor', $p->shiftFractionTowards('grainlineBottom', 'grainlineTop',0.2));
        $p->addTitle('titleAnchor', 3, $this->t($p->title), '2x '.$this->t('from fabric')."\n".' 2x '.$this->t('from lining'), ['scale' => 75]);
        $p->addPoint('logoAnchor', $p->shiftFractionTowards('grainlineBottom', 'grainlineTop',0.8));
        $p->newSnippet('logo', 'logo', 'logoAnchor');

        // Notes
        if($this->o('sa')) {
            $p->newNote( $p->newId(), 'sideWaistSideBackCpTop', $this->t("Standard seam allowance")."\n(".$p->unit($this->o('sa')).')', 9, 10, -5);
            $p->newNote( $p->newId(), 'grainlineBottom', $this->t("Extra hem allowance")."\n(".$p->unit($this->o('sa')*3).')', 11, 30, -23);
        }

        // Notches
        $p->notch(['sideWaistSideBack','waistSideBack']);
    }
    /**
     * Finalizes the topsleeve
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function finalizeTopsleeve($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['topsleeve'];

        // Sleeve front notch
        $len = $p->curveLen('sleeveTop','sleeveTopCpLeft','frontPitchPointCpTop','frontPitchPoint');
        if($len == $this->v('frontSleevecapToNotch') + $this->o('sleevecapEase')/2) $p->clonePoint('frontPitchPoint', 'frontSleeveNotch');
        elseif ($len > $this->v('frontSleevecapToNotch') + $this->o('sleevecapEase')/2) $p->addPoint('frontSleeveNotch', $p->shiftAlong('sleeveTop','sleeveTopCpLeft','frontPitchPointCpTop','frontPitchPoint',  $this->v('frontSleevecapToNotch') + $this->o('sleevecapEase')/2));
        else $p->addPoint('frontSleeveNotch', $p->shiftAlong('frontPitchPoint', 'frontPitchPointCpBottom','topsleeveLeftEdge', 'topsleeveLeftEdge', ($this->v('frontSleevecapToNotch') + $this->o('sleevecapEase')/2)-$len));
        $p->notch(['frontSleeveNotch']);

        // Sleeve back notch
        $this->setValue('backSleevecapPithToNotch', false);
        $len = $p->curveLen('sleeveTop','sleeveTopCpRight','backPitchPoint','backPitchPoint');
        if($len == $this->v('backSleevecapToNotch') + $this->o('sleevecapEase')/2) $p->clonePoint('backPitchPoint', 'backSleeveNotch');
        elseif ($len > $this->v('backSleevecapToNotch') + $this->o('sleevecapEase')/2)  $p->addPoint('backSleeveNotch', $p->shiftAlong('sleeveTop','sleeveTopCpRight','backPitchPoint','backPitchPoint',  $this->v('backSleevecapToNotch') + $this->o('sleevecapEase')/2));
        else $this->setValue('backSleevecapPithToNotch', ($this->v('backSleevecapToNotch') + $this->o('sleevecapEase')/2) - $len);
        if($this->v('backSleevecapPithToNotch') === false) $p->notch(['backSleeveNotch']);
        
        if($this->o('sa')) {
            // 4cm extra hem allowance
            $p->offsetPathString('hemsa','M topsleeveWristLeft L topsleeveWristRight',$this->o('sa')*4,0);
            $p->addPoint('hemSaLeftIn', $p->beamsCross('topsleeveWristLeft','topsleeveElbowLeft', 'hemsa-startPoint', 'hemsa-endPoint'));
            $angleLeft = $p->angle('hemSaLeftIn', 'topsleeveWristLeft') - $p->angle('topsleeveWristRight', 'topsleeveWristLeft');
            $p->addPoint('hemSaLeft', $p->rotate('hemSaLeftIn', 'topsleeveWristLeft', $angleLeft*-2));
            $p->addPoint('hemSaRightIn', $p->beamsCross('ventBottomRight','ventTopRight', 'hemsa-startPoint', 'hemsa-endPoint'));
            $angleRight = $p->angle('ventBottomRight', 'hemSaRightIn') - $p->angle('topsleeveWristLeft', 'topsleeveWristRight');
            $p->addPoint('hemSaRight', $p->rotate('hemSaRightIn', 'ventBottomRight', $angleRight*-2));
            
            // Seam allowance
            $p->offsetPathString('sa1', 'M elbowRight C elbowRightCpTop topsleeveRightEdgeCpBottom topsleeveRightEdge C topsleeveRightEdgeCpTop backPitchPoint backPitchPoint C backPitchPoint sleeveTopCpRight sleeveTop C sleeveTopCpLeft frontPitchPointCpTop frontPitchPoint C frontPitchPointCpBottom topsleeveLeftEdgeCpRight topsleeveLeftEdge C topsleeveLeftEdge topsleeveElbowLeftCpTop topsleeveElbowLeft L topsleeveWristLeft L hemSaLeft L hemSaRight L ventBottomRight L ventTopRight L ventTopLeft L elbowRight z', $this->o('sa')*-1, 1, ['class' => 'fabric sa']);
            $p->newPath('hemHint', 'M topsleeveWristLeft L hemSaLeft L hemSaRight L ventBottomRight', ['class' => 'hint']);
        }

        // Notes
        $p->newNote( $p->newId(), 'frontSleeveNotch', $this->t("Work in sleevecap ease\nfrom this point onwards"), 4, 20, 5);
        if($p->isPoint('backSleeveNotch')) $p->newNote( $p->newId(), 'backSleeveNotch', $this->t("Work in sleevecap ease\nfrom this point onwards"), 8, 20, 5);
        else $p->newNote( $p->newId(), 'backPitchPoint', $this->t("Work in sleevecap ease\nfrom this point onwards"), 8, 20, 5);
        if($this->o('sa')) {
            $p->newNote( $p->newId(), 'topsleeveRightEdgeCpBottom', $this->t("Standard seam allowance")."\n(".$p->unit($this->o('sa')).')', 8, 20, -3);
            $p->newNote( $p->newId(), 'topsleeveWristLeftHelperBottom', $this->t("Extra hem allowance")."\n(".$p->unit($this->o('sa')*5).')', 12, 40, -20);
        }
        // Title and logo
        $p->addTitle('underarmCenter', 4, $this->t($p->title), '2x '.$this->t('from fabric')."\n".' 2x '.$this->t('from lining'));
        $p->newSnippet('logo', 'logo', 'elbowCenter');

        // Grainline 
        $p->newPoint('grainlineBottom', $p->x('sleeveTop'), $p->y('topsleeveWristLeft'));
        $p->newGrainline('grainlineBottom','sleeveTop', $this->t('Grainline'));
    }

    /**
     * Finalizes the undersleeve
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function finalizeUndersleeve($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['undersleeve'];

        // Should we notch the sleevecap?
        if($this->v('backSleevecapPithToNotch') !== false) {
            $p->addPoint('backSleeveNotch', $p->shiftAlong('undersleeveTip','undersleeveTipCpBottom','undersleeveLeftEdgeCpRight','undersleeveLeftEdgeRight', $this->v('backSleevecapPithToNotch')));
            $p->notch(['backSleeveNotch']);
        }

        if($this->o('sa')) {
            // 4cm extra hem allowance
            $p->offsetPathString('hemsa','M undersleeveWristLeft L undersleeveWristRight',$this->o('sa')*4,0);
            $p->addPoint('hemSaLeftIn', $p->beamsCross('undersleeveWristLeft','undersleeveElbowLeft', 'hemsa-startPoint', 'hemsa-endPoint'));
            $p->addPoint('hemSaRightIn', $p->beamsCross('ventBottomRight','ventTopRight', 'hemsa-startPoint', 'hemsa-endPoint'));
            $angleLeft = $p->angle('undersleeveWristLeft', 'hemSaLeftIn') - $p->angle('undersleeveWristLeft', 'undersleeveWristRight');
            $p->addPoint('hemSaLeft', $p->rotate('hemSaLeftIn', 'undersleeveWristLeft', $angleLeft*-2));
            $angleRight = $p->angle('undersleeveWristRight', 'hemSaRightIn') - $p->angle('undersleeveWristRight', 'undersleeveWristLeft');
            $p->addPoint('hemSaRight', $p->rotate('hemSaRightIn', 'undersleeveWristRight', $angleRight*-2));

            // Seam allowance
            $p->offsetPathString('sa1', 'M elbowRight C elbowRightCpTop undersleeveRightEdgeCpBottom undersleeveRightEdge C undersleeveRightEdgeCpTop undersleeveTip undersleeveTip C undersleeveTipCpBottom undersleeveLeftEdgeCpRight undersleeveLeftEdgeRight L undersleeveLeftEdge C undersleeveLeftEdge undersleeveElbowLeftCpTop undersleeveElbowLeft L undersleeveWristLeft L hemSaLeft L hemSaRight L ventBottomRight L ventTopRight L ventTopLeft L elbowRight z', $this->o('sa')*-1,1, ['class' => 'fabric sa']);
            $p->newPath('hemHint', 'M undersleeveWristLeft L hemSaLeft L hemSaRight L ventBottomRight', ['class' => 'hint']);
        } 

        // Grainline
        $p->newPoint('grainlineBottom', $p->x('undersleeveLeftEdgeCpRight'), $p->y('undersleeveWristLeft'));
        $p->clonePoint('undersleeveLeftEdgeCpRight','grainlineTop');
        $p->newGrainline('grainlineBottom','grainlineTop', $this->t('Grainline'));
        
        // Title and logo
        $p->addPoint('titleAnchor', $p->shiftFractionTowards('grainlineBottom','undersleeveLeftEdgeCpRight',0.8));
        $p->addTitle('titleAnchor', 5, $this->t($p->title), '2x '.$this->t('from fabric')."\n".' 2x '.$this->t('from lining'));
        $p->addPoint('logoAnchor', $p->shiftFractionTowards('grainlineBottom','undersleeveLeftEdgeCpRight',0.3));
        $p->newSnippet('logo', 'logo', 'logoAnchor');
        
        // Notes
        if($this->o('sa')) {
            $p->newNote( $p->newId(), 'elbowRight', $this->t("Standard seam allowance")."\n(".$p->unit($this->o('sa')).')', 8, 20, -3);
            $p->newNote( $p->newId(), 'grainlineBottom', $this->t("Extra hem allowance")."\n(".$p->unit($this->o('sa')*5).')', 1, 40, -20);
        }
    }

    /**
     * Finalizes the undercollar
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function finalizeUndercollar($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['undercollar'];

        // Seam allowance
        if($this->o('sa')) {
            $p->offsetPathString('sa1', 'M bottomLeft L notchPoint L notchTip', $this->o('sa'), 1, ['class' => 'various sa']);
            $p->offsetPathString('sa2', 'M m.bottomLeft L m.notchPoint L m.notchTip', $this->o('sa')*-1, 1, ['class' => 'various sa']);
            $p->newPath('sa3', 'M notchTip L sa1-endPoint M bottomLeft L sa1-startPoint M m.bottomLeft L sa2-startPoint M m.notchTip L sa2-endPoint', ['class' => 'various sa']);
        }

        // Grainline
        $p->newGrainline('collarCbBottom','collarCbTop', $this->t('Grainline'));

        // Title
        $p->addPoint('titleAnchor', $p->shiftFractionTowards('collarCbTop','m.shoulderLineRealLeft', 0.5));
        $p->addTitle('titleAnchor', 6, $this->t($p->title), '1x '.$this->t('from fixme'), ['scale' => 75]);
    }

    /**
     * Finalizes the collar
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function finalizeCollar($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['collar'];

        // Seam allowance
        if($this->o('sa')) $p->offsetPath('sa', 'outline', $this->o('sa')*-1, 1, ['class' => 'fabric sa']);
    
        // Grainline
        $p->newGrainline('ucTop','collarCbTop', $this->t('Grainline'));
        
        // Title
        $p->addPoint('titleAnchor', $p->shiftFractionTowards('collarCbTop','m.bottomLeft', 0.3));
        $p->addTitle('titleAnchor', 7, $this->t($p->title), '1x '.$this->t('from fabric'),['scale' => 50]);
    }

    /**
     * Finalizes the collarstand
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function finalizeCollarstand($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['collarstand'];

        // Seam allowance
        if($this->o('sa')) $p->offsetPath('sa', 'outline', $this->o('sa'), 1, ['class' => 'fabric sa']);
    
        // Grainline
        $p->newGrainline('collarCbBottom','ucTop', $this->t('Grainline'));
        
        // Title
        $p->addPoint('titleAnchor', $p->shiftFractionTowards('ucTop','m.ucBottomCurve6', 0.6));
        $p->addTitle('titleAnchor', 8, $this->t($p->title), '1x '.$this->t('from fabric'),['scale' => 30]);
    }

    /**
     * Finalizes the pocket
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function finalizePocket($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['pocket'];

        // Seam allowance
        if($this->o('sa')) $p->offsetPath('sa', 'outline', $this->o('sa')*-1, 1, ['class' => 'fabric sa']);

        // Grainline 
        $p->addPoint('glBot', $p->shift('leftArcBot', 0, 10));
        $p->newPoint('glTop', $p->x('glBot'), $p->y('edgeLeft'));
        $p->newGrainline('glBot','glTop', $this->t('Grainline'));

        // Fold over here text
        $p->newTextOnPath('fold', 'M topLeft L topRight', $this->t('Fold over along this line'), ['text-anchor' => 'middle', 'dy' => -2], false);

        // Title
        $p->addPoint('titleAnchor', $p->shiftFractionTowards('topLeft','bottomRight', 0.5));
        $p->addTitle('titleAnchor', 9, $this->t($p->title), '2x '.$this->t('from fabric'),['scale' => 100]);

    }

    /**
     * Finalizes the chest pocket welt
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function finalizeChestPocketWelt($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['chestPocketWelt'];
        
        // Seam allowance
        if($this->o('sa')) $p->offsetPath('sa', 'outline', $this->o('sa')*-1, 1, ['class' => 'fabric sa']);

        // Grainline
        $p->addPoint('glBot', $p->shift('botLeft', 0, 15));
        $p->addPoint('glTop', $p->shift('midLeft', 0, 15));
        $p->addPoint('glTop', $p->beamsCross('glBot','glTop','topLeft', 'topRight'));
        $p->newGrainline('glBot','glTop', $this->t('Grainline'));
        
        // Title
        $p->addPoint('titleAnchor', $p->shiftFractionTowards('topLeft','botRight', 0.5));
        $p->addTitle('titleAnchor', 10, $this->t($p->title), '2x '.$this->t('from fabric'),['scale' => 50]);
    }

    /**
     * Finalizes the chest pocket bag
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function finalizeChestPocketBag($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['chestPocketBag'];

        // Seam allowance
        if($this->o('sa')) $p->offsetPathString('sa', 'M topLeft L topRight L botRight L botLeft z', $this->o('sa'), 1, ['class' => 'lining sa']);

        // Title
        $p->addPoint('titleAnchor', $p->shiftFractionTowards('topLeft','botRight', 0.5));
        $p->addTitle('titleAnchor', 11, $this->t($p->title), '2x '.$this->t('from lining'),['scale' => 75]);

        $p->newHeightDimension('botLeft','topLeft',20, $p->unit(self::CHEST_POCKET_DEPTH*2));
    }

    /**
     * Finalizes the inner pocket welt
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function finalizeInnerPocketWelt($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['innerPocketWelt'];
        
        // Seam allowance
        if($this->o('sa')) {
            $p->offsetPathString('sa', 'M topLeft L topRight L botRight L botLeft z', $this->o('sa'), 1, ['class' => 'lining sa']);
            // Straighten corners
            $p->newPoint('sa-line-topLeftTOtopRight', $p->x('topLeft')-$this->o('sa'), $p->y('topLeft')-$this->o('sa'));
            $p->newPoint('sa-line-topRightTOtopLeft', $p->x('topRight')+$this->o('sa'), $p->y('topLeft')-$this->o('sa'));
            $p->newPoint('sa-line-botLeftTObotRight', $p->x('botLeft')-$this->o('sa'), $p->y('botLeft')+$this->o('sa'));
            $p->newPoint('sa-line-botRightTObotLeft', $p->x('botRight')+$this->o('sa'), $p->y('botLeft')+$this->o('sa'));
        }
        
        // Title
        $p->addPoint('titleAnchor', $p->shiftFractionTowards('topLeft','botRight', 0.5));
        $p->addTitle('titleAnchor', 12, $this->t($p->title), '2x '.$this->t('from lining'),['scale' => 40]);
    }

    /**
     * Finalizes the inner pocket bag
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function finalizeInnerPocketBag($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['innerPocketBag'];

        // Seam allowance
        if($this->o('sa')) $p->offsetPathString('sa', 'M topLeft L topRight L botRight L botLeft z', $this->o('sa'), 1, ['class' => 'lining sa']);

        // Title
        $p->addPoint('titleAnchor', $p->shiftFractionTowards('topLeft','botRight', 0.5));
        $p->addTitle('titleAnchor', 13, $this->t($p->title), '2x '.$this->t('from lining'),['scale' => 75]);

    }

    /**
     * Finalizes the inner pocket facing extension
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function finalizeInnerPocketFacingExtension($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['innerPocketFacingExtension'];
        
        // Seam allowance
        if($this->o('sa')) {
            $p->offsetPath('sa', 'outline', $this->o('sa'), 1, ['class' => 'fabric sa']);
            // Fix SA at tabs
            $tabLength = $p->distance('ipfeBotLeftBot','ipfeTopLeftTop');
            $angle = $p->angle('ipfeBotLeftBot','ipfeTopLeftTop');
            $p->addPoint('sa-line-ipfeTopLeftTopTOipfeBotLeftBot', $p->shift('sa-line-ipfeTopLeftTopTOipfeBotLeftBot', $angle, $this->o('sa')));
            $p->addPoint('sa-curve-ipfeTopLeftTopTOipfeTopLeftBot', $p->shift('sa-curve-ipfeTopLeftTopTOipfeTopLeftBot', $angle, $this->o('sa')));
            $p->addPoint('sa-line-ipfeBotLeftBotTOipfeTopLeftTop', $p->shift('sa-line-ipfeBotLeftBotTOipfeTopLeftTop', $angle, $this->o('sa')*-1));
            $p->addPoint('sa-curve-ipfeBotLeftBotTOipfeBotLeftTop', $p->shift('sa-curve-ipfeBotLeftBotTOipfeBotLeftTop', $angle, $this->o('sa')*-1));
        }
        
        // Title
        $p->addPoint('titleAnchor', $p->shiftFractionTowards('weltTop','ipTopRight', 0.5));
        $p->addTitle('titleAnchor', '', $this->t($p->title), $this->t('Attach to front facing prior to cutting'),['scale' => 65, 'noPatternTitle' => true]);
    }



    /*
        ____                       _
       |  _ \ __ _ _ __   ___ _ __| | ___  ___ ___
       | |_) / _` | '_ \ / _ \ '__| |/ _ \/ __/ __|
       |  __/ (_| | |_) |  __/ |  | |  __/\__ \__ \
       |_|   \__,_| .__/ \___|_|  |_|\___||___/___/
                  |_|

      Instructions for paperless patterns
    */

    /**
     * Adds paperless info for the back
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function paperlessBack($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['back'];

        // Height on the left
        $xBase = $p->x('chestCenter');
        if($this->o('sa')) $xBase -= $this->o('sa');
        $p->newHeightDimension('chestCenter', 'centerBackNeck', $xBase-15);
        $p->newHeightDimension('chestCenter', 8, $xBase-30);
        $p->newHeightDimension('waistCenter','chestCenter', $xBase-15);
        if($this->o('backVent') == 1) {
            $p->newHeightDimension('ventTip','chestCenter', $xBase-30);
            $p->newHeightDimension('ventFacing-endPoint','chestCenter', $xBase-45);
            $p->newHeightDimension('ventFacingBottomLeft','chestCenter', $xBase-60);
        } else {
            $p->newHeightDimension('hemCenter','chestCenter', $xBase-30);
        }

        // Heights on the right
        $xBase = $p->x('slArm');
        if($this->o('sa')) $xBase += $this->o('sa');
        $p->newHeightDimension('slArm','chestCenter',$xBase+15);
        $p->newHeightDimension('slArm',10,$xBase+30);
        $p->newHeightDimension('slArm',12,$xBase+45);
        $p->newHeightDimension('slArm',8,$xBase+60);
        $p->newHeightDimension('waistBackSide','slArm',$xBase+15);
        if($this->o('backVent') == 2) {
            $p->newHeightDimension('ventTip','slArm', $xBase+30);
            $p->newHeightDimension('ventFacing-endPoint','slArm', $xBase+45);
            $xBase+=30;
            $p->newWidthDimension('hemBackSide','ventFacingBottomRight', $p->y('ventFacingBottomRight')+15+$this->o('sa')*3);
        }
        $p->newHeightDimension('hemBackSide','slArm',$xBase+30);

        // Widths
        $yBase = $p->y(8);
        if($this->o('sa')) $yBase -= $this->o('sa');
        $p->newWidthDimensionSm('chestCenter','centerBackNeck', $yBase-15);
        $p->newWidthDimension('chestCenter',8, $yBase-30);
        $p->newWidthDimension('chestCenter',10, $yBase-45);
        $p->newWidthDimension('chestCenter',12, $yBase-60);
        $offset = 15;
        if($this->o('sa')) $offset += $this->o('sa');
        $p->newLinearDimension(8,12,$offset*-1);
        $p->newLinearDimension('waistCenter','waistBackSide', -15);
        $hemOffset = 15;
        if($this->o('sa')) $hemOffset += $this->o('sa')*3;
        $p->newLinearDimension('hemCenter','hemBackSide', $hemOffset);
        if($this->o('backVent') == 1) $p->newWidthDimension('ventFacingBottomLeft', 'hemCenter', $p->y('hemCenter')+$hemOffset);
    }

    /**
     * Adds paperless info for the front
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function paperlessFront($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['front'];
        
        // Height on the left
        $xBase = $p->x('breakPoint');
        if($this->o('sa')) $xBase -= $this->o('sa');
        $p->newHeightDimension(3, 'breakPoint',$xBase-15);
        $p->newHeightDimension(3,'cpBottomLeft',$xBase-30);
        $p->newHeightDimension(3,'cpBottomRight',$xBase-45);
        $p->newHeightDimension(3,'cfRealTop',$xBase-60);
        $p->newHeightDimension(3,'collarCorner',$xBase-75);
        $p->newHeightDimension(3,'shoulderLineRealLeft',$xBase-90);
        $p->newHeightDimension('roundRight', 3,$xBase-15);
        
        // Height on the right
        $xBase = $p->x('slArm');
        if($this->o('sa')) $xBase += $this->o('sa');
        $p->newHeightDimension('slArm', 10,$xBase+15);
        $p->newHeightDimension('slArm', 'shoulderLineRight',$xBase+30);
        $p->newHeightDimension('slArm', 'shoulderLineRealLeft',$xBase+45);
        $p->newHeightDimension('waistBackSide','slArm',$xBase+15);
        $p->newHeightDimension('frontSideHem','waistBackSide',$xBase+15);
        
        // Widths
        $yBase = $p->y('shoulderLineRealLeft');
        if($this->o('sa')) $yBase -= $this->o('sa');
        $p->newWidthDimensionSm('cfRealTop',9, $yBase+35);
        $p->newWidthDimensionSm(9,'notchPoint', $yBase+35);
        $p->newWidthDimension(9,'shoulderLineRealLeft', $yBase-15);
        $p->newWidthDimension(9,10, $yBase-30);
        $p->newWidthDimension(9,'shoulderLineRight', $yBase-45);
        $p->newWidthDimension(9,'slArm', $yBase-60);
        $p->newLinearDimension('shoulderLineRealLeft','shoulderLineRight', -20);
        $p->newLinearDimensionSm('shoulderLineRealLeft','facingTop', 15);
        $p->newLinearDimension(3,'waistBackSide',-5);
        $p->newWidthDimensionSm('breakPoint',9, $p->y(3)-5);
        $p->newWidthDimension('cfHem','roundRight', $p->y('frontSideHem')-10);
        $hemOffset = 20;
        if($this->o('sa')) $hemOffset += $this->o('sa')*3;
        $p->newWidthDimension('cfHem','frontSideHem', $p->y('roundRight')+$hemOffset);

        // Pocket and dart
        $p->newWidthDimension('cfHem','fpBottomLeft', $p->y('fpBottomLeft')+10);
        $p->newWidthDimension(9,'frontDartBottom', $p->y('frontDartBottom')+10);
        $p->newHeightDimension('frontDartBottom','frontDartRight', $p->x('frontDartRight')+15);
        $p->newHeightDimension('frontDartRight', 'frontDartTop', $p->x('frontDartRight')+15);
        $p->newWidthDimensionSm('frontDartLeft','frontDartRight', $p->y('frontDartTop')-10);
        $p->newHeightDimensionSm('fpTopRight', 'waistBackSide', $xBase);
        $p->newHeightDimension('fpBottomRight','fpTopRight', $xBase);
        $p->newLinearDimension('cpTopLeft','cpTopRight', -10);
        $p->newLinearDimensionSm('cpBottomRight','cpTopRightOrig', 10);
        $p->newHeightDimension('frontDartRight', 'ipMidRight', $p->x('frontDartRight')+30);
    }

    /**
     * Adds paperless info for the side
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function paperlessSide($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['side'];

        // Height on the right
        $xBase = $p->x('sideHemSideBack');
        if($this->o('sa')) $xBase += $this->o('sa');
        $p->newHeightDimension('sideWaistSideBack', 'side14',$xBase+15);
        if($this->o('backVent') == 2) {
            $xBase = $p->x('ventFacingBottomRight')+15+$this->o('sa');
            $p->newHeightDimension('sideHemSideBack', 'ventFacing-startPoint',$xBase);
            $p->newHeightDimension('sideHemSideBack', 'ventTip',$xBase+15);
            $xBase += 15;
            $p->newWidthDimension('sideHemSideBack', 'ventFacingBottomRight', $p->y('ventFacingBottomRight')+15+$this->o('sa')*3);
        }
        $p->newHeightDimension('sideHemSideBack', 'sideWaistSideBack',$xBase+15);

        // Height on the left
        $xBase = $p->x('sideFrontHem');
        if($this->o('sa')) $xBase -= $this->o('sa');
        $p->newHeightDimension('sideFrontHem', 'waistSideBack',$xBase-15);
        $p->newHeightDimension('waistSideBack', 'slArm',$xBase-15);

        // Widths
        $p->newWidthDimension('sideFrontHem','sideHemSideBack', $p->y('sideFrontHem')+3*$this->o('sa')+15);
        $p->newWidthDimension('waistSideBack','sideWaistSideBack', $p->y('sideWaistSideBack')-15);
        $p->newWidthDimension('slArm','side14', $p->y('side14')-15-$this->o('sa'));
        $p->addPoint('outerRight', $p->curveEdge('side14','sideSlArmCpBottom','sideWaistSideBackCpTop','sideWaistSideBack','right'));
        $p->newWidthDimension('slArm','outerRight', $p->y('side14')-30-$this->o('sa'));

    }

    /**
     * Adds paperless info for the topsleeve
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function paperlessTopsleeve($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['topsleeve'];

        // Heigh left side
        $xBase = $p->x('topsleeveLeftEdge') - 15;
        if($this->o('sa')) {
            $xBase -= $this->o('sa');
            $sa = $this->o('sa');
        } else $sa = 0;
        // Height left
        $p->newHeightDimension('topsleeveWristLeft', 'topsleeveLeftEdge', $xBase);
        $p->newHeightDimension('topsleeveLeftEdge', 'sleeveTop', $xBase);
        $p->newHeightDimension('topsleeveRightEdge', 'sleeveTop', $p->x('topsleeveRightEdge')+15+$sa);
        $p->newHeightDimension('elbowRight', 'topsleeveRightEdge', $p->x('topsleeveRightEdge')+15+$sa);

        $p->newWidthDimension('topsleeveLeftEdge','sleeveTop', $p->y('sleeveTop')-15-$sa);
        $p->newWidthDimension('sleeveTop', 'backPitchPoint', $p->y('sleeveTop')-15-$sa);
        $p->newWidthDimension('sleeveTop', 'topsleeveRightEdge', $p->y('sleeveTop')-30-$sa);
        $p->newWidthDimension('topsleeveLeftEdge', 'topsleeveRightEdge', $p->y('sleeveTop')-45-$sa);

        // Linear
        $p->newLinearDimension('topsleeveLeftEdge', 'topsleeveRightEdge');
        $p->newLinearDimension('topsleeveElbowLeft', 'elbowRight');
        $p->newLinearDimension('undersleeveWristRight', 'elbowRight', 15+$sa);
        $p->newLinearDimension('topsleeveWristLeft', 'topsleeveElbowLeft', -15-$sa);
        $p->newLinearDimension('topsleeveWristLeft', 'topsleeveWristRight', -15);
        $p->newLinearDimension('ventBottomRight', 'ventTopRight', 15+$sa);
        $p->newLinearDimension('topsleeveWristLeft', 'ventBottomRight', 15+5*$sa);


    }

    /**
     * Adds paperless info for the undersleeve
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function paperlessUndersleeve($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['undersleeve'];
        
        // Heigh left side
        $xBase = $p->x('undersleeveElbowLeft') - 15;
        if($this->o('sa')) {
            $xBase -= $this->o('sa');
            $sa = $this->o('sa');
        } else $sa = 0;
        // Height left
        $p->newHeightDimension('undersleeveLeftEdge', 'undersleeveTip', $xBase-15);
        $p->newHeightDimension('undersleeveElbowLeft', 'undersleeveLeftEdge', $xBase);

        $p->newWidthDimension('undersleeveLeftEdge','grainlineTop', $p->y('undersleeveTip')-$sa-15);
        $p->newWidthDimension('grainlineTop','undersleeveTip', $p->y('undersleeveTip')-$sa-15);
        $p->newWidthDimension('undersleeveLeftEdge','undersleeveTip', $p->y('undersleeveTip')-$sa-30);
        $p->newLinearDimension('undersleeveLeftEdge','undersleeveRightEdge');
        $p->addPoint('rightEdge', $p->curveEdge('elbowRight','elbowRightCpTop','undersleeveRightEdgeCpBottom','undersleeveRightEdge','right'));
        $p->newWidthDimension('undersleeveLeftEdge','rightEdge', $p->y('undersleeveTip')-$sa-45);
        $p->newLinearDimension('undersleeveElbowLeft','elbowRight');
        $p->newLinearDimension('undersleeveWristLeft','undersleeveWristRight', -15);
        $p->newLinearDimension('undersleeveWristLeft', 'undersleeveElbowLeft', -15-$sa/2);
        $p->newLinearDimension('undersleeveWristRight', 'elbowRight', 15+$sa/2);
        $p->newLinearDimension('undersleeveWristLeft', 'ventBottomRight', 15+5*$sa);
        $p->newLinearDimension('ventBottomRight', 'ventTopRight', 15+$sa);
        
        // Note
        $p->newNote(1, 'topsleeveWristLeftHelperBottom', $this->o('sleeveBend').' '.$this->t('degree').' '.$this->t('slant'), 6, 30);
    }

    /**
     * Adds paperless info for the undercollar
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function paperlessUndercollar($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['undercollar'];

        // Width
        $p->newWidthDimension('bottomLeft','m.bottomLeft',$p->y('bottomLeft')+15);
        $p->newWidthDimension('notchPoint','m.notchPoint',$p->y('bottomLeft')+30);
        $p->newWidthDimension('notchTip','m.notchTip',$p->y('collarCbTop')-15);

        //Height
        $p->newLinearDimension('collarCbBottom','collarCbTop', 15);
        $xBase = $p->x('m.notchPoint')+$this->o('sa');
        $p->newHeightDimension('m.notchTip','collarCbTop', $xBase+15);
        $p->newHeightDimension('m.notchPoint','collarCbTop', $xBase+30);
        $p->newHeightDimension('m.bottomLeft','collarCbTop', $xBase+45);

    }

    /**
     * Adds paperless info for the collar
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function paperlessCollar($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['collar'];

        // Width
        $p->newWidthDimension('ucBottomLeft','m.ucBottomLeft',$p->y('bottomLeft')+15);
        $p->newWidthDimension('bottomLeft','m.bottomLeft',$p->y('bottomLeft')+30);
        $p->newWidthDimension('notchPoint','m.notchPoint',$p->y('bottomLeft')+45);
        $p->newWidthDimension('notchTip','m.notchTip',$p->y('collarCbTop')-15);

        //Height
        $p->newLinearDimension('ucTop','collarCbTop', 15);
        $xBase = $p->x('m.notchPoint')+$this->o('sa');
        $p->newHeightDimension('m.notchTip','collarCbTop', $xBase+15);
        $p->newHeightDimension('m.notchPoint','collarCbTop', $xBase+30);
        $p->newHeightDimension('m.ucBottomLeft','collarCbTop', $xBase+45);
        $p->newHeightDimension('m.bottomLeft','collarCbTop', $xBase+60);
    }

    /**
     * Adds paperless info for the collarstand
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function paperlessCollarstand($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['collarstand'];

        $p->newLinearDimensionSm('collarCbBottom','ucTop',15);
        $p->newWidthDimension('ucBottomLeft','m.ucBottomLeft', $p->y('ucBottomLeft')+15+$this->o('sa'));
        $p->newHeightDimension('m.ucBottomLeft','ucTop',$p->x('m.ucBottomLeft')+15+$this->o('sa'));
    }

    /**
     * Adds paperless info for the pocket
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function paperlessPocket($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['pocket'];

        $p->newWidthDimension('leftArcTop','rightArcTop', $p->y('leftArcBot')+15+$this->o('sa'));
        $p->newHeightDimension('topRight','edgeRight', $p->x('topRight')+15+$this->o('sa'));
        $p->newHeightDimension('rightArcBot','edgeRight', $p->x('topRight')+30+$this->o('sa'));
    }
    
    /**
     * Adds paperless info for the chest pocket welt
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function paperlessChestPocketWelt($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['chestPocketWelt'];

        $p->newWidthDimension('botLeft','botRight', $p->y('botLeft')+15+$this->o('sa'));
        $p->newHeightDimension('botRight','topRight', $p->x('midRight')+15+$this->o('sa'));
        $p->newNote('slant','glTop',$this->t('Grainline slanted by ').$this->o('chestPocketAngle').$this->t('degrees'),2,14);
    }
    
    /**
     * Adds paperless info for the chest pocket bag
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function paperlessChestPocketBag($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['chestPocketBag'];

        $p->newLinearDimension('botRight','topRight', -15, $p->unit(2 * (self::CHEST_POCKET_DEPTH + $p->deltaY('topRight','topLeft'))));
        $p->newWidthDimension('botLeft','botRight',$p->y('botRight')+15+$this->o('sa'));
    }
    
    /**
     * Adds paperless info for the inner pocket welt
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function paperlessInnerPocketWelt($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['innerPocketWelt'];
        
        $p->newWidthDimension('botLeft','botRight',$p->y('botRight')+15+$this->o('sa'));
        $p->newHeightDimension('botRight','topRight',$p->x('botRight')+15+$this->o('sa'));
    }
    
    /**
     * Adds paperless info for the inner pocket bag
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function paperlessInnerPocketBag($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['innerPocketBag'];

        $p->newWidthDimension('botLeft','botRight',$p->y('botRight')+15+$this->o('sa'));
    }
    
    /**
     * Adds paperless info for the inner pocket facingExtension
     *
     * @param \Freesewing\Model $model The model to draft for
     *
     * @return void
     */
    public function paperlessInnerPocketFacingExtension($model)
    {
        /** @var \Freesewing\Part $p */
        $p = $this->parts['innerPocketFacingExtension'];
        
        $p->newWidthDimension('weltMid','ipfeBotRightTop',$p->y('ipfeBotLeftBot')+15+$this->o('sa'));
        $p->newHeightDimension('ipfeBotRightBot','ipfeTopRightTop', $p->x('ipfeBotRightTop')+15+$this->o('sa'));
    }
}
