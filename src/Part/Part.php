<?php
/** Freesewing\Part class */
namespace Freesewing;

use Freesewing\Boundary;
use Freesewing\Note;
use Freesewing\Path;
use Freesewing\Point;
use Freesewing\Stack;
use Freesewing\SvgInclude;
use Freesewing\SvgSnippet;
use Freesewing\Text;
use Freesewing\TextOnPath;
use Freesewing\Transform;

/**
 * Parts are what patterns are made of.
 *
 * This part class is crucial in constructing patterns.
 * A part is a pattern piece (for example the yoke on a shirt).
 * It is a self-contained unit that has all the information to render
 * that piece of the pattern.
 * It also has a bunch of helper functions that make the life of a
 * pattern designer easier.
 *
 * @author    Joost De Cock <joost@decock.org>
 * @copyright 2016 Joost De Cock
 * @license   http://opensource.org/licenses/GPL-3.0 GNU General Public License, Version 3
 */
class Part
{

    /** @var array List of points */
    public $points = [];

    /** @var array List of snippets */
    public $snippets = [];

    /** @var array List of texts */
    public $texts = [];

    /** @var array List of textsOnPath */
    public $textsOnPath = [];

    /** @var array List of paths */
    public $paths = [];

    /** @var array List of transforms */
    public $transforms = [];

    /** @var array List of dimensions */
    public $dimensions = [];

    /** @var array Holds temporary variables */
    public $tmp = [];

    /** @var array notes */
    public $notes = [];

    /** @var string The part title */
    public $title = null;

    /** @var Boundary The part boundary */
    public $boundary = array();

    /** @var bool Percentage our path offset is allowed to deviate */
    public $maxOffsetTolerance = 5;

    /** @var int Number of steps when walking a path */
    public $steps = 1000;

    /** @var bool To render this part or not */
    private $render = true;

    /** @var string The units, either 'metric' or 'imperial' */
    private $units = 'metric';

    /**
     * Sets the render property.
     *
     * @param bool $bool true to render, false to not render
     */
    public function setRender($bool)
    {
        $this->render = $bool;
    }

    /**
     * Returns the render property.
     *
     * @return bool True to render, false to not render
     */
    public function getRender()
    {
        return $this->render;
    }

    /**
     * Sets the title property.
     *
     * @param string
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * Returns the title property.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Sets the units property.
     *
     * @param string
     */
    public function setUnits($units)
    {
        $this->units = $units;
    }

    /**
     * Takes a value in mm and returns it as text in the chosen units
     *
     * For example, this returns 25.4 as either '2.54cm' or '1"'
     *
     * @param string $val The value to convert
     *
     * @return string $text The converted text
     */
    public function unit($val)
    {
        if ($this->units == 'imperial') {
            return round($val / 25.4, 2) . '"';
        } else {
            return round($val / 10, 2) . 'cm';
        }
    }

    /**
     * Creates a path and adds it to $this->paths.
     *
     * This takes a pathString and optional attrinutes
     * and creates a new \Freesewing\Path with it
     * that it then adds to the paths array with key $key
     *
     * @param string $key        Index in the paths array
     * @param string $pathString The pathstring in unrendered format
     * @param array  $attributes Optional attributes of the path
     */
    public function newPath($key, $pathString, $attributes = null)
    {
        $path = new Path();
        $path->setPath($pathString);
        $path->setAttributes($attributes);
        $this->addPath($key, $path);
    }

    /**
     * Creates a path and adds it to $this->points.
     *
     * This takes a x and y coordinate with optional description
     * and creates a new \Freesewing\Point with it
     * that it then adds to the points array with key $key
     *
     * @param string $key         Index in the points array
     * @param float  $x           X-coordinate
     * @param float  $y           Y-coordinate
     * @param string $description Optional description for the point
     */
    public function newPoint($key, $x, $y, $description = null)
    {
        $point = new Point();
        $point->setX($x);
        $point->setY($y);
        $point->setDescription($description);
        $this->addPoint($key, $point);
    }

    /**
     * Creates a snippet and adds it to $this->snippets.
     *
     * This takes a defs reference
     * (the id of something defined in the defs section of the SVG document)
     * and anchor with optional attributes and description
     * and creates a new \Freesewing\Snippet with it
     * that it then adds to the snippets array with key $key
     *
     * @param string $key         Index in the snippets array
     * @param string $reference   defs reference
     * @param string $anchorKey   Key of the point to anchor this on
     * @param array  $attributes  Optional array of attributes for the snippet
     * @param string $description Optional description for the point
     */
    public function newSnippet($key, $reference, $anchorKey, $attributes = null, $description = null)
    {
        $snippet = new SvgSnippet();
        $snippet->setReference($reference);
        $snippet->setAnchor($this->loadPoint($anchorKey));
        $snippet->setDescription($description);
        $snippet->setAttributes($attributes);
        $this->addSnippet($key, $snippet);
    }

    /**
     * Creates an include and adds it to $this->includes.
     *
     * This takes content and creates a new \Freesewing\SvgInclude with it
     * that it then adds to the includes array with key $key
     *
     * @param string $key     Index in the includes array
     * @param string $content SVG content to include
     */
    public function newInclude($key, $content)
    {
        $include = new SvgInclude();
        $include->set($content);
        $this->addInclude($key, $include);
    }

    /**
     * Creates text and adds it to $this->texts.
     *
     * This takes text, anchor reference and optional attributes
     * and creates a new \Freesewing\Text with it
     * that it then adds to the texts array with key $key
     *
     * @param string $key        Index in the texts array
     * @param string $anchorKey  Key of the point to anchor this on
     * @param string $msg        The message of the text
     * @param array  $attributes Optional array of attributes for the snippet
     */
    public function newText($key, $anchorKey, $msg, $attributes = null)
    {
        $text = new Text();
        $text->setAnchor($this->loadPoint($anchorKey));
        $text->setText($msg);
        $text->setAttributes($attributes);
        $this->addText($key, $text);
    }

    /**
     * Creates a note and adds it to $this->notes.
     *
     * This takes text, anchor reference and optional attributes
     * along with a direction, length, and offset
     * and creates a new \Freesewing\Note with it
     * that it then adds to the notes array with key $key
     *
     * @param string $key        Index in the notes array
     * @param string $anchorKey  Key of the point to anchor this on
     * @param string $msg        The message of the note
     * @param string $direction  Direction to where the note arrow points
     * @param string $length     Length of the note arrow
     * @param string $offset     How far from the anchor does the note arrow start
     * @param array  $attributes Optional array of attributes for the snippet
     */
    public function newNote($key, $anchorKey, $msg, $direction = 3, $length = 25, $offset = 3, $attributes = ['class' => 'note', 'line-height' => 7])
    {
        $note = new Note();

        if ($direction >= 1 && $direction <= 12) {
            $angle = -30 * $direction + 90;
        } else {
            $angle = 0;
        }
        $fromId = $this->newId('.note');
        $this->addPoint($fromId, $this->shift($anchorKey, $angle, $offset));
        $toId = $this->newId('.note');
        $this->addPoint($toId, $this->shift($anchorKey, $angle, $length));
        $path = new Path();
        $path->setPath("M $fromId L $toId");
        $path->setAttributes(['class' => 'note']);
        $note->setPath($path);

        $textAnchorId = $this->newId('.note');
        $this->addPoint($textAnchorId, $this->shift($anchorKey, $angle, $length + 5));
        $anchor = $this->loadPoint($textAnchorId);
        $note->setAnchor($anchor);
        $note->setText($msg);
        if (!isset($attributes['class'])) {
            $attributes['class'] = "note note-$direction";
        } else {
            $attributes['class'] .= " note note-$direction";
        }
        $note->setAttributes($attributes);

        $this->addNote($key, $note);
    }

    /**
     * Creates textOnPath and adds it to $this->notes.
     *
     * This takes text, path string and optional attributes
     * and creates a new \Freesewing\TextOnPath with it
     * that it then adds to the textsOnPath array with key $key
     *
     * @param string $key        Index in the notes array
     * @param string $pathString An unrendered SVG path string
     * @param string $msg        The message of the note
     * @param array  $attributes Optional array of attributes for the TextOnPath
     */
    public function newTextOnPath($key, $pathString, $msg, $attributes = null)
    {
        $textOnPath = new TextOnPath();
        $path = new Path();
        $path->setPath($pathString);
        $textOnPath->setPath($path);
        $textOnPath->setText($msg);
        $textOnPath->setAttributes($attributes);
        $this->addTextOnPath($key, $textOnPath);
    }

    /**
     * Returns a point object.
     *
     * This takes a x and y coordinate with optional description
     * and creates a new \Freesewing\Point with it
     * that it then returns
     *
     * @param float  $x           X-coordinate
     * @param float  $y           Y-coordinate
     * @param string $description Optional description for the point
     *
     * @return Point
     */
    public function createPoint($x, $y, $description = null)
    {
        $point = new Point();
        $point->setX($x);
        $point->setY($y);
        $point->setDescription($description);

        return $point;
    }

    /**
     * Adds a title to the part, made up of a nr, title and message
     *
     * This takes 3 elements (a nr, a short title, and a longer message)
     * and adds them as 3 texts to the patter anchored at $anchorKey
     * Don't confuse this with the title attribute of a part, which is
     * internal. This gets rendered on the pattern.
     *
     * @param string $anchorKey ID of the point to anchor the title on
     * @param string $nr        Number of the part to print on the pattern
     * @param string $title     Title of the part to print on the pattern
     * @param string $msg       Message to print on the pattern
     * @param string $mode      Possible modes: default, vertical, horizontal
     */
    public function addTitle($anchorKey, $nr, $title, $msg = '', $mode = 'default')
    {
        switch ($mode) {
            case 'vertical':
            case 'vertical-small':
                if($mode == 'vertical-small') $class = 'vertical small';
                else $class = 'vertical';
                if ($title != '') {
                    $msg = "\n$msg";
                }
                $anchor = $this->loadPoint($anchorKey);
                $x = $anchor->getX();
                $y = $anchor->getY();
                $this->newText('partNumber', $anchorKey, $nr, ['class' => "part-nr $class"]);
                $this->newText(
                    'partTitle', $anchorKey, $title,
                    ['class' => "part-title $class", 'transform' => "rotate(-90 $x $y)"]
                );
                $this->newText('partMsg', $anchorKey, $msg, ['class' => "part-msg $class", 'transform' => "rotate(-90 $x $y)"]);
                break;
            case 'horizontal':
            case 'horizontal-small':
                if($mode == 'horizontal-small') $class = 'horizontal small';
                else $class = 'horizontal';
                $this->newText('partNumber', $anchorKey, $nr, ['class' => "part-nr $class"]);
                $this->newText('partTitle', $anchorKey, $title, ['class' => "part-title $class"]);
                $this->newText('partMsg', $anchorKey, $msg, ['class' => "part-msg $class"]);
                break;
            case 'small':
            default:
                if($mode == 'small') $class = 'small';
                else $class = '';
                $this->newText('partNumber', $anchorKey, $nr, ['class' => "part-nr $class"]);
                $this->newText('partTitle', $anchorKey, $title, ['class' => "part-title $class"]);
                $this->newText('partMsg', $anchorKey, $msg, ['class' => "part-msg $class"]);
        }
    }

    /**
     * Adds a \Freesewing\Point to $this->points.
     *
     * This takes a pre-created point object
     * and adds it to the points array with key $key.
     * If the optional description is set, it will set the description on the point
     *
     * @param string $key         Index in the points array
     * @param Point  $point       The point object
     * @param string $description Optional description for the point
     */
    public function addPoint($key, Point $point, $description = null)
    {
        if ($description !== null) {
            $point->setDescription($description);
        }
        $this->points[$key] = $point;
    }

    /**
     * Adds a \Freesewing\Snippet to $this->snippets.
     *
     * This takes a pre-created snippet object
     * and adds it to the snippets array with key $key.
     *
     * @param string     $key     Index in the snippets array
     * @param SvgSnippet $snippet The snippet object
     */
    public function addSnippet($key, SvgSnippet $snippet)
    {
        $this->snippets[$key] = $snippet;
    }

    /**
     * Adds a \Freesewing\SvgInclude to $this->includes.
     *
     * This takes a pre-created SvgInclude object
     * and adds it to the includes array with key $key.
     *
     * @param string     $key     Index in the includes array
     * @param SvgInclude $include The include object
     */
    public function addInclude($key, SvgInclude $include)
    {
        $this->includes[$key] = $include;
    }

    /**
     * Adds a \Freesewing\Text to $this->texts.
     *
     * This takes a pre-created text object
     * and adds it to the texts array with key $key.
     *
     * @param string $key  Index in the includes array
     * @param Text   $text The text object
     */
    public function addText($key, Text $text)
    {
        $this->texts[$key] = $text;
    }

    /**
     * Adds a \Freesewing\Note to $this->notes.
     *
     * This takes a pre-created note object
     * and adds it to the notes array with key $key.
     *
     * @param string $key  Index in the notes array
     * @param Note   $note The note object
     */
    public function addNote($key, Note $note)
    {
        $this->notes[$key] = $note;
    }

    /**
     * Adds a \Freesewing\TextOnPath to $this->textsOnPath.
     *
     * This takes a pre-created TextOnPath object
     * and adds it to the textsOnPath array with key $key.
     *
     * @param string     $key        Index in the textsOnPath array
     * @param TextOnPath $textOnPath The TextOnPath object
     */
    public function addTextOnPath($key, TextOnPath $textOnPath)
    {
        $this->textsOnPath[$key] = $textOnPath;
    }

    /**
     * Adds a \Freesewing\Path to $this->paths.
     *
     * This takes a pre-created Path object
     * and adds it to the paths array with key $key.
     *
     * @param string $key  Index in the paths array
     * @param Path   $path The path object
     */
    public function addPath($key, Path $path)
    {
        $this->paths[$key] = $path;
    }

    /**
     * Adds a \Freesewing\Transform to $this->transforms.
     *
     * This takes a pre-created transform object
     * and adds it to the transforms array with key $key.
     *
     * @param string                $key       Index in the transforms array
     * @param Transform $transform The transform object
     */
    public function addTransform($key, Transform $transform)
    {
        $this->transforms[$key] = $transform;
    }

    /**
     * Calculates a bounding box and adds it as a \Freesewing\Boundary to $this->boundary.
     *
     * This figures out the bounding box for a part and adds it as a boundary object
     *
     * @param float $margin The margin to add to the part
     *
     * @return void
     */
    public function addBoundary($margin = 0.0)
    {
        if (count($this->paths) == 0) {
            return;
        }

        // initialize topLeft
        $topLeft = new Point('topLeft');
        $topLeft->setX(INF);
        $topLeft->setY(INF);

        // initialize bottomRight
        $bottomRight = new Point('bottomRight');
        $bottomRight->setX(-INF);
        $bottomRight->setY(-INF);

        // FIXME We need a getAllPaths() method, since notes can also have paths that
        // should be taken into account when calculating the bounding box
        if(is_array($this->dimensions) && count($this->dimensions) > 0) {
            foreach($this->dimensions as $dimension) $dimensionPaths[] = $dimension->getPath();
            $allPaths = array_merge($this->paths,$dimensionPaths);
        } else {
            $allPaths = $this->paths;
        }

        foreach ($allPaths as $path) {
            $path->setBoundary($path->findBoundary($this));

            // topLeft
            if ($path->boundary->topLeft->getX() < $topLeft->getX()) {
                $topLeft->setX($path->boundary->topLeft->getX());
            }
            if ($path->boundary->topLeft->getY() < $topLeft->getY()) {
                $topLeft->setY($path->boundary->topLeft->getY());
            }

            // bottomRight
            if ($path->boundary->bottomRight->getX() > $bottomRight->getX()) {
                $bottomRight->setX($path->boundary->bottomRight->getX());
            }
            if ($path->boundary->bottomRight->getY() > $bottomRight->getY()) {
                $bottomRight->setY($path->boundary->bottomRight->getY());
            }
        }

        $topLeft->setX($topLeft->getX() - $margin);
        $topLeft->setY($topLeft->getY() - $margin);
        $bottomRight->setX($bottomRight->getX() + $margin);
        $bottomRight->setY($bottomRight->getY() + $margin);

        $this->boundary = new Boundary();
        $this->boundary->setTopLeft($topLeft);
        $this->boundary->setBottomRight($bottomRight);
    }

    /**
     * Returns true if a part has a path that needs to be rendered
     *
     * This avoids things breaking when no paths are rendered
     * and thus a bounding box can't be calculated.
     * Just check whether this returns true
     *
     * @return bool true or false
     */
    public function hasPathToRender()
    {
        $render = 0;
        foreach($this->paths as $path) {
            if($path->getRender()) $render++;
        }
        if($render) return true;
        else return false;
    }

    /**
     * Like offsetPath() but takes a pathstring rather than a \Freesewing\Path object
     *
     * @param string $key        Index in the paths array
     * @param string $pathString The unrendered SVG pathstring
     * @param float  $distance   The distance to offset the path by
     * @param bool   $render     Render property of the new path
     * @param array  $attributes Optional array of path attributes
     * 
     * @throws \Exception
     */
    public function offsetPathString($key, $pathString, $distance = 10.0, $render = false, $attributes = null)
    {
        $this->newPath('.offsetHelper', $pathString);
        $this->paths['.offsetHelper']->setRender(false);
        if($this->offsetPath($key, '.offsetHelper', $distance, $render, $attributes) === false) {
            throw new \InvalidArgumentException("Could not offset pathstring: $pathString");
        }
    }

    /**
     * Creates a new path offset from an exsiting path
     *
     * @param string    $newKey     Index in the paths array for the new path
     * @param string    $srcKey     Index in the paths array of the source path
     * @param float|int $distance   The distance to offset the path by
     * @param bool      $render     Render property of the new path
     * @param array     $attributes Optional array of path attributes
     *
     * @throws \Exception
     */
    public function offsetPath($newKey, $srcKey, $distance = 10, $render = false, $attributes = null)
    {
        if(isset($this->paths[$srcKey]) && ($this->paths[$srcKey] instanceof \Freesewing\Path)) {
            $path = $this->paths[$srcKey];
        } else {
            throw new \InvalidArgumentException("offsetPath requires a valid path object");
        }
        $stack = $this->pathOffsetAsStack($path, $distance, $newKey);
        if($stack === false) return false; // Happens for paths like: M 1 L 1
        /* take care of overlapping parts */
        $stack = $this->fixStackIntersections($stack);
        $stack = $this->fillPathStackGaps($stack, $path);
        $pathString = $this->pathStackToPath($stack, $path);
        $this->newPath($newKey, $pathString, $attributes);
        if (!$render) {
            $this->paths[$newKey]->setRender(false);
        }
        $this->purgePoints('.tmp_');
        // Add aliases for start and end point
        $this->clonePoint($this->paths[$newKey]->getStartPoint(),"$newKey-startPoint");
        $this->clonePoint($this->paths[$newKey]->getEndPoint(),"$newKey-endPoint");
    }

    /**
     * @param Stack $stack
     *
     * @return mixed
     */
    private function fixStackIntersections(Stack $stack)
    {
        /**
         * A few assumptions here:
         *
         * - the index of a is lower than that of b (should be the case)
         * - we are removing what's between a and b, not what's between b and a
         */
        $stack = $this->findAllStackIntersections($stack);
        
        foreach ($stack->intersections as $intersection) {
            $delta = $intersection['b'] - $intersection['a'];
            $a = $stack->items[$intersection['a']];
            $b = $stack->items[$intersection['b']];
            foreach ($intersection['at'] as $key => $point) {
                $this->addPoint($key, $point);
                // split a here
                if ($a['type'] == 'curve') {
                    $this->addSplitCurve($a['offset'][0], $a['offset'][1], $a['offset'][2], $a['offset'][3], $key, "$key-a-");
                    $new[] = [
                        'type'         => 'curve',
                        'offset'       => ["$key-a-1", "$key-a-2", "$key-a-3", "$key-a-4"],
                        'intersection' => true
                    ];
                    $stack->replace($a, $new);
                    unset($new);
                } else {
                    $new[] = ['type' => 'line', 'offset' => [$a['offset'][0], $key], 'intersection' => true];
                    $stack->replace($a, $new);
                    unset($new);
                }
                // split b here
                if ($b['type'] == 'curve') {
                    $this->addSplitCurve($b['offset'][0], $b['offset'][1], $b['offset'][2], $b['offset'][3], $key, "$key-b-");
                    $new[] = [
                        'type'         => 'curve',
                        'offset'       => ["$key-b-8", "$key-b-7", "$key-b-6", "$key-b-5"],
                        'intersection' => true
                    ];
                    $stack->replace($b, $new);
                    unset($new);
                } else {
                    $new[] = ['type' => 'line', 'offset' => [$key, $b['offset'][1]]];
                    $stack->replace($b, $new);
                    unset($new);
                }
            }
        }

        if(isset($delta) && $delta > 1) {
            // Intersecting path segments are not adjacent in the stack
            // We need to remove the chunks in between
            for($i=1;$i<$delta;$i++) {
                // Removing this would mess up the indexes, so we'll replace it with nothing
                $stack->replace($stack->items[$i+$intersection['a']], ['type' => ['type' => 'removed']]);
            }
        }

        return $stack;
    }

    /**
     * @param Stack $stack
     *
     * @return mixed
     */
    private function findAllStackIntersections(Stack $stack)
    {
        $count = count($stack->items);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $intersections = $this->findStackIntersections($stack->items[$i], $stack->items[$j]);
                if (is_array($intersections)) {
                    $stack->addIntersection(['a' => $i, 'b' => $j, 'at' => $intersections]);
                }
            }
        }

        return $stack;
    }

    /**
     * Finds intersections between two steps in a path stack
     *
     * @param array $s1 A step on the stack
     * @param array $s2 A step on the stack
     *
     * @return array $intersections An array of intersections
     */
    private function findStackIntersections($s1, $s2)
    {
        if ($s1['type'] == 'line' && $s2['type'] == 'line') {
            $intersections = $this->findLineLineStackIntersections($s1,$s2);
        } elseif ($s1['type'] == 'curve' && $s2['type'] == 'curve') {
            $intersections = $this->findCurveCurveStackIntersections($s1,$s2);
        } elseif ($s1['type'] == 'line' && $s2['type'] == 'curve') {
            $intersections = $this->findLineCurveStackIntersections($s1, $s2);
        } else {
            $intersections = $this->findCurveLineStackIntersections($s1, $s2);
        }
        return $intersections;
    }

    /**
     * Finds intersections between two curves in a path stack
     *
     * @param array $s1 A curve on the stack
     * @param array $s2 A curve on the stack
     *
     * @return array $intersections An array of intersections
     */
    private function findCurveCurveStackIntersections($s1, $s2)
    {
        if ($this->curveLen(
            $s1['offset'][0], $s1['offset'][1], $s1['offset'][2],
            $s1['offset'][3]
        ) > 10 and $this->curveLen(
            $s2['offset'][0], $s2['offset'][1], $s2['offset'][2],
            $s2['offset'][3]
        ) > 10
        ) {
            $i = BezierToolbox::findCurveCurveIntersections(
                $this->loadPoint($s1['offset'][0]), $this->loadPoint($s1['offset'][1]), $this->loadPoint($s1['offset'][2]), $this->loadPoint($s1['offset'][3]),
                $this->loadPoint($s2['offset'][0]), $this->loadPoint($s2['offset'][1]), $this->loadPoint($s2['offset'][2]), $this->loadPoint($s2['offset'][3])
            );
            if ($i) {
                foreach ($i as $key => $point) {
                    // Ignore intersections at curve end points
                    if (Utils::isSamePoint($point, $this->loadPoint($s1['offset'][0])) or Utils::isSamePoint(
                        $point,
                        $this->loadPoint($s1['offset'][3])
                    ) or Utils::isSamePoint(
                        $point,
                        $this->loadPoint($s2['offset'][0])
                    ) or Utils::isSamePoint(
                        $point,
                        $this->loadPoint($s2['offset'][3])
                    )
                    ) {
                        unset($i[$key]);
                    }
                }
                if(count($i)>0) return $this->keyArray($i, 'intersection-');
            }
        }

        return false;
    }

    /**
     * Finds intersections between a curve and a line in a path stack
     *
     * @param array $s1 A curve on the stack
     * @param array $s2 A line on the stack
     *
     * @return array $intersections An array of intersections
     */
    private function findCurveLineStackIntersections($s1, $s2)
    {
        if ($this->curveLen($s1['offset'][0], $s1['offset'][1], $s1['offset'][2], $s1['offset'][3]) > 10) {
            $i = BezierToolbox::findLineCurveIntersections(
                $this->loadPoint($s2['offset'][0]), $this->loadPoint($s2['offset'][1]),
                $this->loadPoint($s1['offset'][0]), $this->loadPoint($s1['offset'][1]), $this->loadPoint($s1['offset'][2]), $this->loadPoint($s1['offset'][3])
            );
            if ($i) {
                foreach ($i as $key => $point) {
                    // Ignore intersections at line/curve end points
                    if (Utils::isSamePoint($point, $this->loadPoint($s1['offset'][0])) or Utils::isSamePoint(
                        $point,
                        $this->loadPoint($s1['offset'][3])
                    ) or Utils::isSamePoint(
                        $point,
                        $this->loadPoint($s2['offset'][0])
                    ) or Utils::isSamePoint(
                        $point,
                        $this->loadPoint($s2['offset'][1])
                    )
                    ) {
                        unset($i[$key]);
                    }
                }
                if(count($i)>0) return $this->keyArray($i, 'intersection-');
            }
        }

        return false;
    }

    /**
     * Finds intersections between a line and a curve in a path stack
     *
     * @param array $s1 A line on the stack
     * @param array $s2 A curve on the stack
     *
     * @return array $intersections An array of intersections
     */
    private function findLineCurveStackIntersections($s1, $s2)
    {
        if ($this->curveLen($s2['offset'][0], $s2['offset'][1], $s2['offset'][2], $s2['offset'][3]) > 10) {
            $i = BezierToolbox::findLineCurveIntersections(
                $this->loadPoint($s1['offset'][0]), $this->loadPoint($s1['offset'][1]), 
                $this->loadPoint($s2['offset'][0]), $this->loadPoint($s2['offset'][1]), $this->loadPoint($s2['offset'][2]), $this->loadPoint($s2['offset'][3])
            );
            if ($i) {
                foreach ($i as $key => $point) {
                    // Ignore intersections at line/curve end points
                    if (Utils::isSamePoint($point, $this->loadPoint($s1['offset'][0])) or Utils::isSamePoint(
                        $point,
                        $this->loadPoint($s1['offset'][1])
                    ) or Utils::isSamePoint(
                        $point,
                        $this->loadPoint($s2['offset'][0])
                    ) or Utils::isSamePoint(
                        $point,
                        $this->loadPoint($s2['offset'][3])
                    )
                    ) {
                        unset($i[$key]);
                    }
                }
                if(count($i)>0) return $this->keyArray($i, 'intersection-');
            }
        }
        return false;
    }

    /**
     * Finds intersections between two lines in a path stack
     *
     * @param array $s1 A line on the stack
     * @param array $s2 A line on the stack
     *
     * @return array $intersections An array of intersections
     */
    private function findLineLineStackIntersections($s1, $s2)
    {
        $i = $this->linesCross($s1['offset'][0], $s1['offset'][1], $s2['offset'][0], $s2['offset'][1]);

        // Do we have an intersection?
        if(!$i) {
            return false;
        }

        // Ignore intersections at line end points
        if (
            Utils::isSamePoint($i, $this->loadPoint($s1['offset'][0])) or 
            Utils::isSamePoint($i, $this->loadPoint($s1['offset'][1])) or 
            Utils::isSamePoint($i, $this->loadPoint($s2['offset'][0])) or 
            Utils::isSamePoint($i, $this->loadPoint($s2['offset'][1]))
        ) {
                return false;
        }
    
        return $this->keyArray(array($i), 'intersection-');
    }

    /**
     * Prefix the keys of
     *
     * @param array  $array
     * @param string $prefix
     *
     * @return array
     */
    private function keyArray($array, $prefix)
    {
        $return = [];
        foreach ($array as $key => $value) {
            $return[$this->newID($prefix)] = $value;
        }

        return $return;
    }

    /**
     * Clears points aray from all points with a given prefix
     *
     * Offsetting a path creates new points, many of them have no use
     * after the path offset is calculated. This allows us to clear them.
     *
     * @param string|bool $prefix Prefix to check for
     */
    private function purgePoints($prefix = false)
    {
        if ($prefix !== false) {
            $len = strlen($prefix);
            foreach ($this->points as $key => $point) {
                if (substr($key, 0, $len) == $prefix) {
                    unset($this->points[$key]);
                }
            }
        }
    }

    /**
     * Joins a stack of single path steps into a complete path
     *
     * To offset a path, we divide it up into single atomic steps that we push on a stack
     * We then offset them all, inserting extra steps  were needed
     * (extra steps are needed when we need to split a curve to offset it accurately)
     * When all is done, we need to re-create a path out of this stack of indidividual steps.
     * This method does that
     *
     * @param Stack $stack       An array of path operations
     * @param Path  $originalPath The original path
     *
     * @return string The contructed pathstring
     */
    private function pathStackToPath(Stack $stack, Path $originalPath)
    {
        /* Is the original path closed? */
        if (substr(trim($originalPath->getPath()), -2) == ' z') {
            $closed = true;
        } else {
            $closed = false;
        }

        $chunks = count($stack->items);
        $count = 1;
        $path = '';
        foreach ($stack->items as $chunk) {
            if ($count == 1) {
                // First step
                if ($chunk['type'] == 'line') {
                    $path = 'M ' . $chunk['offset'][0] . ' L ' . $chunk['offset'][1];
                } elseif ($chunk['type'] == 'curve') {
                    $path = 'M ' . $chunk['offset'][0] . ' C ' . $chunk['offset'][1] . ' ' . $chunk['offset'][2] . ' ' . $chunk['offset'][3];
                }
            } elseif ($count == $chunks) {
                // Last step
                if ($chunk['type'] == 'line') {
                    $path .= ' L ' . $chunk['offset'][1];
                    if ($closed) {
                        $path .= ' z';
                    }
                } elseif ($chunk['type'] == 'curve') {
                    $path .= ' C ' . $chunk['offset'][1] . ' ' . $chunk['offset'][2] . ' ' . $chunk['offset'][3];
                    // On a closed path, the last step is always a line, as we close path with a line, not curve
                }
            } else {
                // All other steps
                if (isset($chunk['type']) && $chunk['type'] == 'line') {
                    $path .= ' L ' . $chunk['offset'][1];
                } elseif (isset($chunk['type']) && $chunk['type'] == 'curve') {
                    $path .= ' C ' . $chunk['offset'][1] . ' ' . $chunk['offset'][2] . ' ' . $chunk['offset'][3];
                }
            }
            ++$count;
        }

        return $path;
    }

    /**
     * Creates a new unused ID with an optional prefix
     *
     * When offsetting a path, or for other operations where we need to add
     * points, we need an ID to add a new point to $this->points.
     * While we don't really care what the idea is, we want to make sure it's not used.
     * Additionally, by providing a prefix, we can clean up these points with $this->purgePoints()
     *
     * @param string $prefix A prefix to apply to the generated ID
     *
     * @return string The new ID
     */
    public function newId($prefix = '.volatile')
    {
        if (isset($this->tmp['id'][$prefix])) {
            ++$this->tmp['id'][$prefix];
        } else {
            $this->tmp['id'][$prefix] = 1;
        }

        return $prefix . $this->tmp['id'][$prefix];
    }

    /**
     * Fills gaps between individually offsetted path steps
     *
     * When offsetting a path, we offset each step seperately.
     * At corners, or bends, this creates 'gaps' in the path.
     * This method adds steps to the stack to fill those gaps.
     *
     * @param Stack $stack An array of individual path steps
     * @param Path  $path  The path
     *
     * @return Stack $stack The updated stack
     */
    private function fillPathStackGaps(Stack $stack, $path)
    {
        $chunks = count($stack->items);
        $count = 1;
        $array = $stack->items;
        foreach ($array as $chunk) {
            if ($count == $chunks) {
                // Last step. Do we need to close the path?
                if ($path->isClosed()) {
                    $next = $array[0]; // We do
                } else {
                    return $stack; // No, we're done
                }
            } else {
                $next = $array[$count];
            }
            if ($chunk['type'] != 'removed') { // removed entries are no longer arrays
                if ($chunk['type'] == 'line' && $next['type'] == 'line') {
                    $new = $this->fillPathStackLineLineGap($chunk,$next); 
                } elseif ($chunk['type'] == 'line' && $next['type'] == 'curve') {
                    $new = $this->fillPathStackLineCurveGap($chunk,$next); 
                } elseif ($chunk['type'] == 'curve' && $next['type'] == 'line') {
                    $new = $this->fillPathStackCurveLineGap($chunk,$next); 
                } elseif ($chunk['type'] == 'curve' && $next['type'] == 'curve') {
                    $new = $this->fillPathStackCurveCurveGap($chunk,$next);
                }
                if ($new) $stack->replace($chunk, $new);
                unset($new);
            }
            ++$count;
        }

        return $stack;
    }

    /**
     * Fills gaps between two offsetted curves in stack
     *
     * When offsetting a path, we offset each step seperately.
     * At corners, or bends, this creates 'gaps' in the path.
     * This method adds steps to the stack to fill a gap between two curves.
     *
     * @param array $chunk The current step in the stack
     * @param array $next The next step in the stack
     *
     * @return array The stack steps to replace the original chunk with
     */
    private function fillPathStackCurveCurveGap($chunk, $next)
    {
        if (
            !$this->isSamePoint($chunk['offset'][3], $next['offset'][0]) && 
            $this->curveLen( $next['offset'][0], $next['offset'][1], $next['offset'][2], $next['offset'][3]) > 0
        ) {
            // Gap to fill
            if ($this->isSamePoint($chunk['offset'][2], $chunk['offset'][3])) {
                // Quadratic Bezier, shift a tiny bit along the curve to get a different point
                $this->addPoint(
                    '.helpChunk',
                    $this->shiftAlong(
                        $chunk['offset'][3], $chunk['offset'][2], $chunk['offset'][1],
                        $chunk['offset'][0], 0.5
                    )
                );
            } else {
                // Cubic Bezier, we can just use the control point
                $this->clonePoint($chunk['offset'][2], '.helpChunk');
            }
            if ($this->isSamePoint($next['offset'][0], $next['offset'][1])) {
                // Quadratic Bezier, shift a tiny bit along the curve to get a different point
                $this->addPoint(
                    '.helpNext',
                    $this->shiftAlong(
                        $next['offset'][0], $next['offset'][1], $next['offset'][2], $next['offset'][3],
                        0.5
                    )
                );
            } else {
                // Cubic Bezier, we can just use the control point
                $this->clonePoint($next['offset'][1], '.helpNext');
            }
            
            $intersectionPoint = $this->beamsCross('.helpChunk', $chunk['offset'][3], '.helpNext', $next['offset'][0]);
            if($intersectionPoint instanceof Point) {
                // Beams do cross, proceed as normal
                $id = $chunk['offset'][3] . 'XccX' . $next['offset'][0];
                $this->addPoint(
                    $id,
                    $this->beamsCross('.helpChunk', $chunk['offset'][3], '.helpNext', $next['offset'][0])
                );
                $new[] = $chunk;
                $new[] = ['type' => 'line', 'offset' => [$chunk['offset'][3], $id]];
                $new[] = ['type' => 'line', 'offset' => [$id, $next['offset'][0]]];
            } else {
                // Beams are parallel. Just connect the start/end points
                $new[] = $chunk;
                $new[] = ['type' => 'line', 'offset' => [$chunk['offset'][3], $next['offset'][0]]];
            }
            return $new;
        }
        return false;
    }

    /**
    * Fills gaps between an offsetted curve and line in stack
     *
     * When offsetting a path, we offset each step seperately.
     * At corners, or bends, this creates 'gaps' in the path.
     * This method adds steps to the stack to fill a gap between a curve and line.
     *
     * @param array $chunk The current step in the stack
     * @param array $next The next step in the stack
     *
     * @return array The stack steps to replace the original chunk with
     */
    private function fillPathStackCurveLineGap($chunk, $next)
    {
        if (!$this->isSamePoint($chunk['offset'][3], $next['offset'][0])) {
            // Gap to fill
            if ($this->isSamePoint($chunk['offset'][2], $chunk['offset'][3])) {
                // Quadratic Bezier, shift a tiny bit along the curve to get a different point
                $this->addPoint(
                    '.help',
                    $this->shiftAlong(
                        $chunk['offset'][3], $chunk['offset'][2], $chunk['offset'][1],
                        $chunk['offset'][0], 0.5
                    )
                );
            } else {
                // Cubic Bezier, we can just use the control point
                $this->clonePoint($chunk['offset'][2], '.help');
            }
            $id = $chunk['offset'][3] . 'XclX' . $next['offset'][0];
            $this->addPoint(
                $id,
                $this->beamsCross($chunk['offset'][3], '.help', $next['offset'][0], $next['offset'][1])
            );
            $new[] = $chunk;
            $new[] = ['type' => 'line', 'offset' => [$chunk['offset'][3], $id]];
            $new[] = ['type' => 'line', 'offset' => [$id, $next['offset'][0]]];
            return $new;
        }
        return false;
    }

    /**
     * Fills gaps between an offsetted line and curve in stack
     *
     * When offsetting a path, we offset each step seperately.
     * At corners, or bends, this creates 'gaps' in the path.
     * This method adds steps to the stack to fill a gap between a line and curve.
     *
     * @param array $chunk The current step in the stack
     * @param array $next The next step in the stack
     *
     * @return array The stack steps to replace the original chunk with
     */
    private function fillPathStackLineCurveGap($chunk, $next)
    {
        if (!$this->isSamePoint($chunk['offset'][1], $next['offset'][0])) {
            // Gap to fill
            /**
             * If the control point falls on the edge (so it's really a Quadratic
             * Bezier rather than a Cubic Bezier, we need a helper point
             * because we want to find the intersection between two lines
             * but two identical points do no make a line.
             * So, we move 0.5mm along the curve to get two different points.
             *
             * This also applies to the curves in the curve-line and curve-curve
             * scenarios below
             */
            if ($this->isSamePoint($next['offset'][0], $next['offset'][1])) {
                // Quadratic Bezier, shift a tiny bit along the curve to get a different point
                $this->addPoint(
                    '.help',
                    $this->shiftAlong(
                        $next['offset'][0], $next['offset'][1], $next['offset'][2], $next['offset'][3],
                        0.5
                    )
                );
            } else {
                // Cubic Bezier, we can just use the control point
                $this->clonePoint($next['offset'][1], '.help');
            }
            $id = $chunk['offset'][1] . 'XlcX' . $next['offset'][0];
            $this->addPoint(
                $id,
                $this->beamsCross($chunk['offset'][0], $chunk['offset'][1], $next['offset'][0], '.help')
            );
            $new[] = $chunk;
            $new[] = ['type' => 'line', 'offset' => [$chunk['offset'][1], $id]];
            $new[] = ['type' => 'line', 'offset' => [$id, $next['offset'][0]]];
            return $new;
        }
        return false;
    }

    /**
     * Fills gaps between 2 offsetted lines in stack
     *
     * When offsetting a path, we offset each step seperately.
     * At corners, or bends, this creates 'gaps' in the path.
     * This method adds steps to the stack to fill a gap between 2 lines.
     *
     * @param array $chunk The current step in the stack
     * @param array $next The next step in the stack
     *
     * @return array The stack steps to replace the original chunk with
     */
    private function fillPathStackLineLineGap($chunk, $next)
    {
        if (!$this->isSamePoint($chunk['offset'][1], $next['offset'][0])) {
            // Gap to fill
            $id = $chunk['offset'][1] . 'XllX' . $next['offset'][0];
            $this->addPoint(
                $id,
                $this->beamsCross($chunk['offset'][0], $chunk['offset'][1], $next['offset'][0], $next['offset'][1])
            );
            $new[] = $chunk;
            $new[] = ['type' => 'line', 'offset' => [$chunk['offset'][1], $id]];
            $new[] = ['type' => 'line', 'offset' => [$id, $next['offset'][0]]];
            return $new;
        } 
        return false;
    }

    /**
     * Breaks up path in as many pieces as needed for an acceptable offset
     *
     * When offsetting a path, we offset each step seperately.
     * These seperated steps are pushed onto a stack.
     * For curves, we need to break them up if the offset is not precise enough.
     * This results in multiple steps on the stack to mimic a single curve
     *
     * @param Path   $path     The path to offset
     * @param float  $distance The distance to offset the path by
     * @param string $key      The key of the new path
     *
     * @return Stack The new ID
     */
    private function pathOffsetAsStack(Path $path, $distance, $key)
    {
        $stack = new Stack();
        foreach ($path->breakUp() as &$chunk) {
            if ($chunk['type'] == 'L') {
                $hop = $this->offsetLine($chunk['path'], $distance, $key);
                if (is_array($hop)) {
                    $stack->push($hop);
                }
            }
            if ($chunk['type'] == 'C') {
                $stack->push($this->offsetCurve($chunk['path'], $distance, $key));
            }
        }

        if(count($stack->items)>0) return $stack;
        else return false;
    }

    /**
     * Offsets a straight line
     *
     * Offsets a straight line segment.  This is rather straightforward.
     *
     * @param string $line     The pathstring of the line segment
     * @param float  $distance The distance to offset the line by
     * @param string $key      The key of the new path
     *
     * @return array|bool An array to go on a stack of path steps
     */
    private function offsetLine($line, $distance, $key)
    {
        $points = Utils::asScrubbedArray($line);
        $from = $points[1];
        $to = $points[3];

        if ($this->isSamePoint($from, $to)) {
            return false; // Sometimes, lines go nowhere
        }
        $offset = $this->getLineOffsetPoints($from, $to, $distance);

        $fromId = "$key-line-$from" . "TO$to";
        $toId = "$key-line-$to" . "TO$from";

        $this->addPoint($fromId, $offset[0]);
        $this->addPoint($toId, $offset[1]);

        return [0 => ['type' => 'line', 'original' => [$from, $to], 'offset' => [$fromId, $toId]]];
    }

    /**
     * Gets the offset for the start and end of a line
     *
     * @param string $from     The ID of the start of the line
     * @param string $to       The ID of the end of the line
     * @param float  $distance The distance to offset the line by
     *
     * @return array An array with the offsetted points
     */
    private function getLineOffsetPoints($from, $to, $distance)
    {
        $angle = $this->angle($from, $to) + 90;

        return [
            $this->shift($from, $angle, $distance),
            $this->shift($to, $angle, $distance),
        ];
    }

    /**
     * Gets the offset points for start and end of a curve control handle
     *
     * When offsetting a curve, we offset the control handle:
     *  - The line from start to control point 1
     *  - The line from end to control point 2
     *  This returns the offsetted points for this
     *
     * @param string $from     The ID of the start of the curve
     * @param string $cp1      The ID of control point 1 of the curve
     * @param string $cp2      The ID of control point 2 of the curve
     * @param string $to       The ID of the end of the curve
     * @param float  $distance The distance to offset the line by
     *
     * @return array An array with the offsetted points
     */
    private function getCurveOffsetPoints($from, $cp1, $cp2, $to, $distance)
    {
        if ($this->isSamePoint($from, $cp1)) {
            $halfA = $this->getNonCubicCurveOffsetPoints('cp1', $from, $cp1, $cp2, $to, $distance);
        } else {
            $halfA = $this->getLineOffsetPoints($from, $cp1, $distance);
        }
        if ($this->isSamePoint($cp2, $to)) {
            $halfB = $this->getNonCubicCurveOffsetPoints('cp2', $from, $cp1, $cp2, $to, $distance);
        } else {
            $halfB = $this->getLineOffsetPoints($cp2, $to, $distance);
        }

        return [
            $halfA[0],
            $halfA[1],
            $halfB[0],
            $halfB[1],
        ];
    }

    /**
     * Gets the offset points for start and end of a curve that has a control point on start or end
     *
     * This is like getCurveOffsetPoints but for curves where:
     *  - Control point 1 is 'missing' (it is identical to the start point)
     *  or
     *  - Control point 2 is 'missing' (it is identical to the end point)
     *  This returns the offsetted points for this
     *
     * @param string $missing  Either 'cp1' or 'cp2' to indicate what control point is missing
     * @param string  $from     The start of the curve
     * @param string  $cp1      Control point 1 of the curve
     * @param string  $cp2      Control point 2 of the curve
     * @param string  $to       The end of the curve
     * @param float  $distance The distance to offset the line by
     *
     * @return array An array with the offsetted points
     */
    private function getNonCubicCurveOffsetPoints($missing, $from, $cp1, $cp2, $to, $distance)
    {
        if ($missing == 'cp1') {
            $this->addPoint('-shifthelper', $this->shiftAlong($from, $cp1, $cp2, $to, 5));
            $angle = $this->angle($from, '-shifthelper') + 90;
            $p = $from;
        } else {
            $this->addPoint('-shifthelper', $this->shiftAlong($to, $cp2, $cp1, $from, 5));
            $angle = $this->angle('-shifthelper', $to) + 90;
            $p = $to;
        }

        $offset = $this->shift($p, $angle, $distance);
        return [$offset, $offset];
    }

    /**
     * Checks whether two points are (almost) the same.
     *
     * Checks whether two points are the same, or close enough to be considered the same.
     * Close enough means less than 0.01 mm difference between their coordinates on each axis.
     *
     * @param string $key1 ID of point 1 in $this->points
     * @param string $key2 ID of point 2 in $this->points
     *
     * @return bool True is they are the same. False if not.
     */
    private function isSamePoint($key1, $key2)
    {
        $point1 = $this->loadPoint($key1);
        $point2 = $this->loadPoint($key2);

        return Utils::isSamePoint($point1, $point2);
    }

    /**
     * Offsets a curve
     *
     * The basics are straighforwards, simply offset the curve's control handles.
     * But that's not perfect, so we walk through the offsetted curve to check that it
     * does not deviate more than $this->maxOffsetTolerance percent
     * If it does, we split the curve and offset the parts individually.
     * For this, this method recursively calls itself. The subdivide parameter keeps track of that.
     * We keep on splitting until we're within our $this->maxOffsetTolerance percent
     * tolerance throughout the entire curve
     *
     * @param string $curve    The pathstring of the curve segment
     * @param float  $distance The distance to offset the line by
     * @param string $key      The key of the new path
     * @param int    $subdivide
     *
     * @return array An array to go on a stack of path steps
     * @throws \Exception
     */
    private function offsetCurve($curve, $distance, $key, $subdivide = 0)
    {
        $chunks = [];
        $points = Utils::asScrubbedArray($curve);
        $from = $points[1];
        $cp1 = $points[3];
        $cp2 = $points[4];
        $to = $points[5];
        $offset = $this->getCurveOffsetPoints($from, $cp1, $cp2, $to, $distance);

        if ($subdivide == 0) {
            // First time around
            $fromId = "$key-curve-$from" . "TO$to";
            $toId = "$key-curve-$to" . "TO$from";
            $this->tmp['origCurve'] = array();
            $this->tmp['origCurve']['from'] = $from;
            $this->tmp['origCurve']['to'] = $to;
        } else {
            // Recursively subdividing
            if ($this->isSamePoint($from, $this->tmp['origCurve']['from'])) {
                $fromId = "$key-curve-" . $this->tmp['origCurve']['from'] . 'TO' . $this->tmp['origCurve']['to'];
            } else {
                $fromId = $this->newId();
            }
            if ($this->isSamePoint($to, $this->tmp['origCurve']['to'])) {
                $toId = "$key-curve-" . $this->tmp['origCurve']['to'] . 'TO' . $this->tmp['origCurve']['from'];
            } else {
                $toId = $this->newId();
            }
        }

        // Avoid volatile IDs
        $cp1Id = "$key-cp1--$from.$cp1.$cp2.$to";
        $cp2Id = "$key-cp2--$from.$cp1.$cp2.$to";

        $this->addPoint($fromId, $offset[0]);
        $this->addPoint($cp1Id, $offset[1]);
        $this->addPoint($cp2Id, $offset[2]);
        $this->addPoint($toId, $offset[3]);

        // Add this chunk to the stack
        $chunks[] = [
            'type'      => 'curve',
            'original'  => [$from, $cp1, $cp2, $to],
            'offset'    => [$fromId, $cp1Id, $cp2Id, $toId],
            'subdivide' => $subdivide
        ];
        // Find out how we're doing
        $tolerance = $this->offsetTolerance($chunks[0], $distance);

        $score = $tolerance['score'];
        if ($score > $this->maxOffsetTolerance) {
            // Not good enough, let's subdivide
            $subdivide++;
            $splitId = '.tmp_' . $key . '.splitcurve:' . $this->newId();
            $this->addSplitCurve($from, $cp1, $cp2, $to, $tolerance['index'], $splitId . '-', true);
            unset($chunks);
            $subDivide = $this->offsetCurve("M $splitId-1 C $splitId-2 $splitId-3 $splitId-4", $distance, $key, $subdivide);
            foreach ($subDivide as $chunk) {
                $chunks[] = $chunk;
            }
            $subDivide = $this->offsetCurve("M $splitId-8 C $splitId-7 $splitId-6 $splitId-5", $distance, $key, $subdivide);
            foreach ($subDivide as $chunk) {
                $chunks[] = $chunk;
            }

        }
        return $chunks;
    }

    /**
     * Finds how much an offset differs from its ideal
     *
     * @param array  $entry Array containing offset and original
     * @param string $distance
     *
     * @return array Array with the worst score and where it lies on the curve
     */
    private function offsetTolerance($entry, $distance)
    {
        $originLen = $this->curveLen($entry['original'][0], $entry['original'][1], $entry['original'][2], $entry['original'][3]);
        $offsetLen = $this->curveLen($entry['offset'][0], $entry['offset'][1], $entry['offset'][2], $entry['offset'][3]);
        
        // If a curve gets too short things go off the rails, so don't bother
        if ($originLen < 10) {
            return ['score' => 1, 'index' => 0.5];
        }

        $originFrom = $this->loadPoint($entry['original'][0]);
        $originCp1  = $this->loadPoint($entry['original'][1]);
        $originCp2  = $this->loadPoint($entry['original'][2]);
        $originTo   = $this->loadPoint($entry['original'][3]);
        $offsetFrom = $this->loadPoint($entry['offset'][0]);
        $offsetCp1  = $this->loadPoint($entry['offset'][1]);
        $offsetCp2  = $this->loadPoint($entry['offset'][2]);
        $offsetTo   = $this->loadPoint($entry['offset'][3]);

        $worstDelta = 0;
        $worstIndex = false;

        for ($i = 1; $i < 5; ++$i) {
            $t = $i / 5;

            $xOrigin = Utils::bezierPoint($t, $originFrom->getX(), $originCp1->getX(), $originCp2->getX(), $originTo->getX());
            $yOrigin = Utils::bezierPoint($t, $originFrom->getY(), $originCp1->getY(), $originCp2->getY(), $originTo->getY());
            $xOffset = Utils::bezierPoint($t, $offsetFrom->getX(), $offsetCp1->getX(), $offsetCp2->getX(), $offsetTo->getX());
            $yOffset = Utils::bezierPoint($t, $offsetFrom->getY(), $offsetCp1->getY(), $offsetCp2->getY(), $offsetTo->getY());
            
            $this->newPoint('.offsetToleranceOrigin', $xOrigin, $yOrigin);
            $this->newPoint('.offsetToleranceOffset', $xOffset, $yOffset);
            
            if($i == 1) {
                $angle = $this->angle($entry['original'][0], '.offsetToleranceOrigin')+90;
            } else {
                $angle = $this->angle('.offsetToleranceOriginPrevious', '.offsetToleranceOrigin')+90;
            }
            $this->clonePoint('.offsetToleranceOrigin','.offsetToleranceOriginPrevious');
            
            $this->addPoint('.offsetToleranceOrigin',$this->shift('.offsetToleranceOrigin',$angle,$distance));

            $offset = $this->distance('.offsetToleranceOrigin', '.offsetToleranceOffset')+$distance;
            $delta = abs($offset/($distance/100) -100);
           
            if($delta>$worstDelta) {
                $worstDelta = round($delta,2);
                $worstIndex = $t;
            }
        }
        
        $this->purgePoints('.offsetTolerance');
        
        return ['score' => $worstDelta, 'index' => $worstIndex];
    }

    /**
     * Calculated the length of a path
     *
     * @param Path   $path     The path of which to calculate the length
     *
     * @return float The path length
     */
    private function pathLen(Path $path)
    {
        $len = 0;
        foreach ($path->breakUp() as $chunk) {
            $points = Utils::asScrubbedArray($chunk['path']);
            if ($chunk['type'] == 'L') {
                $len += $this->distance($points[1],$points[3]);
            }
            if ($chunk['type'] == 'C') {
                $len += $this->curveLen($points[1],$points[3],$points[4],$points[5]);
            }
        }

        return $len;
    }

    /*  Helper functions for pattern designers start here  */


    /**
     * Clones a point
     *
     * @param string $sourceKey The id of the source point
     * @param string $targetKey The id of the cloned point
     */
    public function clonePoint($sourceKey, $targetKey)
    {
        if(isset($this->points[$sourceKey])) {
            $this->points[$targetKey] = $this->points[$sourceKey];
        }
        else return false;
    }

    /**
     * Loads a point object from its id
     *
     * @param string $key The id of the point to load
     *
     * @return Point
     * @throws \InvalidArgumentException
     */
    public function loadPoint($key)
    {
        if (isset($this->points[$key])) {
            return $this->points[$key];
        } else {
            throw new \InvalidArgumentException("Cannot load point $key, it does not exist");
        }
    }

    /**
     * Returns the X-coordinate of a point
     *
     * @param string $key The id of the point
     *
     * @return float The X-coordinate
     */
    public function x($key)
    {
        return $this->points[$key]->getX();
    }

    /**
     * Returns the Y-coordinate of a point
     *
     * @param string $key The id of the point
     *
     * @return float The Y-coordinate
     */
    public function y($key)
    {
        return $this->points[$key]->getY();
    }

    /**
     * Returns the distance between two points
     *
     * @param string $key1 The id of the first point
     * @param string $key2 The id of the second point
     *
     * @return float Distance between the points
     */
    public function distance($key1, $key2)
    {
        $point1 = $this->loadPoint($key1);
        $point2 = $this->loadPoint($key2);

        return Utils::distance($point1, $point2);
    }

    /**
     * Returns the distance along the X-axis between two points
     *
     * @param string $key1 The id of the first point
     * @param string $key2 The id of the second point
     *
     * @return float Distance between the points along the X-axis
     */
    public function deltaX($key1, $key2)
    {
        $point1 = $this->loadPoint($key1);
        $point2 = $this->loadPoint($key2);

        return $point2->getX() - $point1->getX();
    }

    /**
     * Returns the distance along the Y-axis between two points
     *
     * @param string $key1 The id of the first point
     * @param string $key2 The id of the second point
     *
     * @return float Distance between the points along the Y-axis
     */
    public function deltaY($key1, $key2)
    {
        $point1 = $this->loadPoint($key1);
        $point2 = $this->loadPoint($key2);

        return $point2->getY() - $point1->getY();
    }

    /**
     * Rotates one point around another
     *
     * @param string $key1     The id of the point to rotate
     * @param string $key2     The id of the pivot point
     * @param float  $rotation The rotation angle in degrees
     *
     * @return Point The rotated point
     */
    public function rotate($key1, $key2, $rotation)
    {
        $point1 = $this->loadPoint($key1);
        $point2 = $this->loadPoint($key2);
        $radius = $this->distance($key1, $key2);
        $angle = $this->angle($key1, $key2);

        $x = $point2->getX() + $radius * cos(deg2rad($angle + $rotation));
        $y = $point2->getY() + $radius * sin(deg2rad($angle + $rotation)) * -1;

        return $this->createPoint($x, $y);
    }

    /**
     * Returns the angle between two points
     *
     * @param string $key1 The id of the first point
     * @param string $key2 The id of the second point
     *
     * @return float The angle
     */
    public function angle($key1, $key2)
    {
        $distance = $this->distance($key1, $key2);
        $deltaX = $this->deltaX($key1, $key2);
        $deltaY = $this->deltaY($key1, $key2);
        $angle = 0;

        if ($deltaX == 0 && $deltaY == 0) {
            $angle = 0;
        } elseif ($deltaX == 0 && $deltaY > 0) {
            $angle = 90;
        } elseif ($deltaX == 0 && $deltaY < 0) {
            $angle = 270;
        } elseif ($deltaY == 0 && $deltaX > 0) {
            $angle = 180;
        } elseif ($deltaY == 0 && $deltaX < 0) {
            $angle = 0;
        } else {
            if ($deltaY > 0) {
                $angle = 180 - rad2deg(acos($deltaX / $distance));
            } elseif ($deltaY < 0) {
                $angle = 180 + rad2deg(acos($deltaX / $distance));
            }
        }

        return $angle;
    }

    /**
     * Returns the length of a curve
     *
     * This loads points and calls the cubicBezierLength()
     * method in our BezierToolbox
     *
     * @param string $keyStart    The id of the start of the curve
     * @param string $keyControl1 The id of the first control point
     * @param string $keyControl2 The id of the second control point
     * @param string $keyEnd      The id of the end of the curve
     *
     * @see \Freesewing\BezierToolbox::cubicBezierLength()
     *
     * @return float The length of the curve
     */
    public function curveLen($keyStart, $keyControl1, $keyControl2, $keyEnd)
    {
        return BezierToolbox::cubicBezierLength(
            $this->loadPoint($keyStart), $this->loadPoint($keyControl1),
            $this->loadPoint($keyControl2), $this->loadPoint($keyEnd)
        );
    }

    /**
     * Shifts a point along a straight line
     *
     * @param string $key1     The id of the first point on the line
     * @param string $key2     The id of the second point on the line
     * @param float  $distance The distance to shift the point
     *
     * @return Point The shifted point
     */
    public function shiftTowards($key1, $key2, $distance)
    {
        $point1 = $this->loadPoint($key1);
        $point2 = $this->loadPoint($key2);
        $angle = $this->angle($key1, $key2);
        // cos is x axis, sin is y axis
        $deltaX = $distance * abs(cos(deg2rad($angle)));
        $deltaY = $distance * abs(sin(deg2rad($angle)));
        if ($point1->getX() < $point2->getX() && $point1->getY() > $point2->getY()) {
            $x = $point1->getX() + abs($deltaX);
            $y = $point1->getY() - abs($deltaY);
        } elseif ($point1->getX() < $point2->getX() && $point1->getY() < $point2->getY()) {
            $x = $point1->getX() + abs($deltaX);
            $y = $point1->getY() + abs($deltaY);
        } elseif ($point1->getX() > $point2->getX() && $point1->getY() > $point2->getY()) {
            $x = $point1->getX() - abs($deltaX);
            $y = $point1->getY() - abs($deltaY);
        } elseif ($point1->getX() > $point2->getX() && $point1->getY() < $point2->getY()) {
            $x = $point1->getX() - abs($deltaX);
            $y = $point1->getY() + abs($deltaY);
        } else {
            $x = $point1->getX() + $deltaX;
            $y = $point1->getY() + $deltaY;
        }

        return $this->createPoint($x, $y, "Point $key1 shifted towards $key2 by $distance");
    }

    /**
     * Shifts a point along a curve
     *
     * As with the length of cubic Bezier curves, this is approximate
     * It's good approximate, but approximate nevertheless
     *
     * @param string $keyStart    The id of the start of the curve
     * @param string $keyControl1 The id of the first control point
     * @param string $keyControl2 The id of the second control point
     * @param string $keyEnd      The id of the end of the curve
     * @param float  $distance    The distance to shift the point
     *
     * @return Point The shifted point
     *
     * @throws \InvalidArgumentException When we shift a point further than the curve is long
     */
    public function shiftAlong($keyStart, $keyControl1, $keyControl2, $keyEnd, $distance)
    {
        $length = 0;
        $start = $this->loadPoint($keyStart);
        $cp1 = $this->loadPoint($keyControl1);
        $cp2 = $this->loadPoint($keyControl2);
        $end = $this->loadPoint($keyEnd);

        $previousX = 0;
        $previousY = 0;

        for ($i = 0; $i <= $this->steps; ++$i) {
            $t = $i / $this->steps;
            $x = Utils::bezierPoint($t, $start->getX(), $cp1->getX(), $cp2->getX(), $end->getX());
            $y = Utils::bezierPoint($t, $start->getY(), $cp1->getY(), $cp2->getY(), $end->getY());
            if ($i > 0) {
                $deltaX = $x - $previousX;
                $deltaY = $y - $previousY;
                $length += sqrt(pow($deltaX, 2) + pow($deltaY, 2));
                if ($length > $distance) {
                    return $this->createPoint(
                        $x, $y,
                        "Point shifted $distance along curve $keyStart $keyControl1 $keyControl2 $keyEnd"
                    );
                }
            }
            $previousX = $x;
            $previousY = $y;
        }
        /* We only arrive here if the curve is shorter than the requested distance */
        //        echo \Freesewing\Utils::debug(debug_backtrace());
        throw new \InvalidArgumentException('Ran out of curve to move along');
    }

    /**
     * Returns the intersection of two lines
     *
     * @param string $key1 The id of the start of line A
     * @param string $key2 The id of the end line A
     * @param string $key3 The id of the start of line B
     * @param string $key4 The id of the end line B
     *
     * @return Point|false The point at the line intersection or false if lines are parallel
     */
    public function linesCross($key1, $key2, $key3, $key4)
    {
        $point = $this->beamsCross($key1, $key2, $key3, $key4);

        if ($point) {
            // We have an intersection, but is it within the lines segments?
            $lenA = $this->distance($key1, $key2);
            $lenB = $this->distance($key3, $key4);
            $this->addPoint('.linesCrossCheck', $point);
            $lenC = $this->distance($key1, '.linesCrossCheck') + $this->distance('.linesCrossCheck', $key2);
            $lenD = $this->distance($key3, '.linesCrossCheck') + $this->distance('.linesCrossCheck', $key4);
            if (round($lenA, 1) == round($lenC, 1) and round($lenB, 1) == round($lenD, 1)) {
                return $point;
            }
        }
        return false;
    }

    /**
     * Returns the intersection of two endless lines (beams)
     *
     * @param string $key1 The id of the start of line A
     * @param string $key2 The id of the end line A
     * @param string $key3 The id of the start of line B
     * @param string $key4 The id of the end line B
     *
     * @return Point|false The point at the line intersection or false if lines are parallel
     */
    public function beamsCross($key1, $key2, $key3, $key4)
    {
        $i = $this->findLineLineIntersection($key1, $key2, $key3, $key4);

        if (is_array($i)) {
            return $this->createPoint($i[0], $i[1]);
        } else {
            return false;
        }
    }

    /**
     * Returns the coordinates of the intersection of two endless lines (beams)
     *
     * @param string $key1 The id of the start of line A
     * @param string $key2 The id of the end line A
     * @param string $key3 The id of the start of line B
     * @param string $key4 The id of the end line B
     *
     * @return array|null The coordinates of the line intersection or null if lines are parallel
     */
    public function findLineLineIntersection($key1, $key2, $key3, $key4)
    {
        return Utils::findLineLineIntersection(
            $this->loadPoint($key1), $this->loadPoint($key2), $this->loadPoint($key3),
            $this->loadPoint($key4)
        );
    }

    /**
     * Returns true if the point exists in $this->points
     *
     * @param string $key The id to look for
     *
     * @return bool True if it exists. False if not.
     */
    public function isPoint($key)
    {
        // Prevent loadPoint from throwing an exception on invalid point id
        if(!isset($this->points[$key])) return false;
        $point = $this->loadPoint($key);
        if ($point instanceof Point) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Mirrors point around a X value
     *
     * @param string $key     The point to flip
     * @param float  $anchorX The X-coordinate to flip around
     *
     * @return Point The flipped point
     */
    public function flipX($key, $anchorX = 0.0)
    {
        $point = $this->loadPoint($key);
        $deltaX = $anchorX - $point->getX();
        $x = $anchorX + $deltaX;

        return $this->createPoint($x, $point->getY(), $point->getDescription());
    }

    /**
     * Mirrors point around a Y value
     *
     * @param string $key     The point to flip
     * @param float  $anchorY The Y-coordinate to flip around
     *
     * @return Point The flipped point
     */
    public function flipY($key, $anchorY = 0.0)
    {
        $point = $this->loadPoint($key);
        $deltaY = $anchorY - $point->getY();
        $y = $anchorY + $deltaY;

        return $this->createPoint($point->getX(), $y, "Point $key flipped around Y coordinate $anchorY");
    }

    /**
     * Returns intersection of a Bezier curve with an X value
     *
     * This adds 2 far away points at the X value so we can simply call
     * our curveCrossesLine() method to do the actual work.
     *
     * @param string      $keyStart    The id of the start of the curve
     * @param string      $keyControl1 The id of the first control point
     * @param string      $keyControl2 The id of the second control point
     * @param string      $keyEnd      The id of the end of the curve
     * @param float       $targetX     The X-value we're looking for
     * @param string|bool $prefix      The prefix for points this will create
     *
     * @return void Nothing Points will be added to the part
     */
    public function curveCrossesX($keyStart, $keyControl1, $keyControl2, $keyEnd, $targetX, $prefix = false)
    {
        $this->newPoint('.curveCrossesX-1', $targetX, -10000);
        $this->newPoint('.curveCrossesX-2', $targetX, 10000);

        $this->curveCrossesLine($keyStart, $keyControl1, $keyControl2, $keyEnd, '.curveCrossesX-1', '.curveCrossesX-2', $prefix);
    }

    /**
     * Returns intersection of a Bezier curve with an Y value
     *
     * This adds 2 far away points at the X value so we can simply call
     * our curveCrossesLine() method to do the actual work.
     *
     * @param string      $keyStart    The id of the start of the curve
     * @param string      $keyControl1 The id of the first control point
     * @param string      $keyControl2 The id of the second control point
     * @param string      $keyEnd      The id of the end of the curve
     * @param float       $targetY     The Y-value we're looking for
     * @param string|bool $prefix      The prefix for points this will create
     *
     * @return void Nothing Points will be added to the part
     */
    public function curveCrossesY($keyStart, $keyControl1, $keyControl2, $keyEnd, $targetY, $prefix = false)
    {
        $this->newPoint('.curveCrossesY-1', -10000, $targetY);
        $this->newPoint('.curveCrossesY-2', 10000, $targetY);

        $this->curveCrossesLine($keyStart, $keyControl1, $keyControl2, $keyEnd, '.curveCrossesY-1', '.curveCrossesY-2', $prefix);
    }

    /**
     * Shifts a point a given distance along given angle
     *
     * @param string $key      The id of the point to shift
     * @param float  $angle    The angle to shift along
     * @param float  $distance The distance to shift the point
     *
     * @return Point The point where the curve crosses the Y-value
     */
    public function shift($key, $angle, $distance)
    {
        $point = $this->loadPoint($key);
        $newPoint = new Point();
        $newPoint->setX($point->getX() + $distance);
        $newPoint->setY($point->getY());
        $this->addPoint('.shiftHelper', $newPoint);

        return $this->rotate('.shiftHelper', $key, $angle);
    }

    /**
     * Returns point that is the left edge of a Bezier curve
     *
     * @param string $curveStartKey    The id of the start of the curve
     * @param string $curveControl1Key The id of the first control point
     * @param string $curveControl2Key The id of the second control point
     * @param string $curveEndKey      The id of the end of the curve
     *
     * @return Point The point at the edge
     */
    public function curveEdgeLeft($curveStartKey, $curveControl1Key, $curveControl2Key, $curveEndKey)
    {
        return $this->curveEdge($curveStartKey, $curveControl1Key, $curveControl2Key, $curveEndKey, 'left');
    }

    /**
     * Returns point that is the right edge of a Bezier curve
     *
     * @param string $curveStartKey    The id of the start of the curve
     * @param string $curveControl1Key The id of the first control point
     * @param string $curveControl2Key The id of the second control point
     * @param string $curveEndKey      The id of the end of the curve
     *
     * @return Point The point at the edge
     */
    public function curveEdgeRight($curveStartKey, $curveControl1Key, $curveControl2Key, $curveEndKey)
    {
        return $this->curveEdge($curveStartKey, $curveControl1Key, $curveControl2Key, $curveEndKey, 'right');
    }

    /**
     * Returns point that is the top edge of a Bezier curve
     *
     * @param string $curveStartKey    The id of the start of the curve
     * @param string $curveControl1Key The id of the first control point
     * @param string $curveControl2Key The id of the second control point
     * @param string $curveEndKey      The id of the end of the curve
     *
     * @return Point The point at the edge
     */
    public function curveEdgeTop($curveStartKey, $curveControl1Key, $curveControl2Key, $curveEndKey)
    {
        return $this->curveEdge($curveStartKey, $curveControl1Key, $curveControl2Key, $curveEndKey, 'top');
    }

    /**
     * Returns point that is the bottom edge of a Bezier curve
     *
     * @param string $curveStartKey    The id of the start of the curve
     * @param string $curveControl1Key The id of the first control point
     * @param string $curveControl2Key The id of the second control point
     * @param string $curveEndKey      The id of the end of the curve
     *
     * @return Point The point at the edge
     */
    public function curveEdgeBottom($curveStartKey, $curveControl1Key, $curveControl2Key, $curveEndKey)
    {
        return $this->curveEdge($curveStartKey, $curveControl1Key, $curveControl2Key, $curveEndKey, 'bottom');
    }

    /**
     * Returns point at the chosen edge of a Bezier curve
     *
     * @param string $curveStartKey    The id of the start of the curve
     * @param string $curveControl1Key The id of the first control point
     * @param string $curveControl2Key The id of the second control point
     * @param string $curveEndKey      The id of the end of the curve
     * @param string $direction        Either left, right, top, or bottom
     *
     * @return Point The point at the edge
     */
    public function curveEdge($curveStartKey, $curveControl1Key, $curveControl2Key, $curveEndKey, $direction)
    {
        return BezierToolbox::findBezierEdge(
            $this->loadPoint($curveStartKey), $this->loadPoint($curveControl1Key),
            $this->loadPoint($curveControl2Key), $this->loadPoint($curveEndKey), $direction
        );
    }

    /**
     * Returns intersection of a Bezier curve with a line
     *
     * @param string      $curveStartKey    The id of the start of the curve
     * @param string      $curveControl1Key The id of the first control point
     * @param string      $curveControl2Key The id of the second control point
     * @param string      $curveEndKey      The id of the end of the curve
     * @param string      $lineStartKey     Point at the start of the line
     * @param string      $lineEndKey       Point at the end of the line
     * @param string|bool $prefix           The prefix for points this will create
     *
     * @return void Nothing Points will be added to the part
     */
    public function curveCrossesLine(
        $curveStartKey,
        $curveControl1Key,
        $curveControl2Key,
        $curveEndKey,
        $lineStartKey,
        $lineEndKey,
        $prefix = false
    ) {
        $points = BezierToolbox::findLineCurveIntersections(
            $this->loadPoint($lineStartKey), $this->loadPoint($lineEndKey),
            $this->loadPoint($curveStartKey), $this->loadPoint($curveControl1Key), $this->loadPoint($curveControl2Key),
            $this->loadPoint($curveEndKey)
        );

        if (is_array($points)) {
            $i = 1;
            foreach ($points as $point) {
                $this->addPoint($prefix . $i, $point);
                $i++;
            }
        }
    }

    /**
     * Splits curve and adds prefixed points for it to $this->points
     *
     * @see \Freesewing\Part::splitCurve()
     *
     * @param string $from         The id of the start of the curve
     * @param string $cp1          The id of the first control point
     * @param string $cp2          The id of the second control point
     * @param string $to           The id of the end of the curve
     * @param string $split        The id of the point to split on, or a delta to split on
     * @param string $prefix       The prefix to add to the new points
     * @param bool   $splitOnDelta True if we're splitting on delta
     */
    public function addSplitCurve($from, $cp1, $cp2, $to, $split, $prefix = '', $splitOnDelta = false)
    {
        $points = $this->splitCurve($from, $cp1, $cp2, $to, $split, $splitOnDelta);
        $this->addPoint($prefix . '1', $points[0]);
        $this->addPoint($prefix . '2', $points[1]);
        $this->addPoint($prefix . '3', $points[2]);
        $this->addPoint($prefix . '4', $points[3]);
        $this->addPoint($prefix . '5', $points[4]);
        $this->addPoint($prefix . '6', $points[5]);
        $this->addPoint($prefix . '7', $points[6]);
        $this->addPoint($prefix . '8', $points[7]);
    }

    /**
     * Splits curve and returns the 8 resulting points
     *
     * Splitting a curve makes two curves,
     * with a start, end and 2 controlpoints each.
     * That's 8 points that we add with a prefix
     * This can split either on a point, in which case $split contains a point ID
     * or on delta, in which case $split contains a delta between 0 and 1
     *
     * @param string     $from         The id of the start of the curve
     * @param string     $cp1          The id of the first control point
     * @param string     $cp2          The id of the second control point
     * @param string     $to           The id of the end of the curve
     * @param string     $split        The id of the point to split on, or a delta to split on
     * @param float|bool $splitOnDelta Whether to split on delta or not
     *
     * @return array the 8 points resulting from the split
     */
    public function splitCurve($from, $cp1, $cp2, $to, $split, $splitOnDelta = false)
    {
        if ($splitOnDelta) {
            $t = $split;
        } else {
            $t = BezierToolbox::cubicBezierDelta(
                $this->loadPoint($from), $this->loadPoint($cp1), $this->loadPoint($cp2),
                $this->loadPoint($to), $this->loadPoint($split)
            );
        }

        $curve1 = BezierToolbox::calculateSplitCurvePoints(
            $this->loadPoint($from), $this->loadPoint($cp1),
            $this->loadPoint($cp2), $this->loadPoint($to), $t
        );
        $t = 1 - $t;
        $curve2 = BezierToolbox::calculateSplitCurvePoints(
            $this->loadPoint($to), $this->loadPoint($cp2), $this->loadPoint($cp1),
            $this->loadPoint($from), $t
        );

        return [
            $curve1[0],
            $curve1[1],
            $curve1[2],
            $curve1[3],
            $curve2[0],
            $curve2[1],
            $curve2[2],
            $curve2[3],
        ];
    }

    /**
     * Returns the distance for a control point to approximate a circle
     *
     * Note that circle is not perfect, but close enough
     *
     * @param float $radius The radius of the circle to aim for
     *
     * @return float The distance to the control point
     */
    public function bezierCircle($radius)
    {
        return BezierToolbox::bezierCircle($radius);
    }

    /**
     * Returns intersections of two cubic Bezier curves
     *
     * @param string      $curve1StartKey    The id of the start of the first curve
     * @param string      $curve1Control1Key The id of the first control point of the first curve
     * @param string      $curve1Control2Key The id of the second control point of the first curve
     * @param string      $curve1EndKey      The id of the end of the first curve
     * @param string      $curve2StartKey    The id of the start of the second curve
     * @param string      $curve2Control1Key The id of the first control point of the second curve
     * @param string      $curve2Control2Key The id of the second control point of the second curve
     * @param string      $curve2EndKey      The id of the end of the second curve
     * @param bool|string $prefix            The prefix for points this will create
     *
     */
    public function curvesCross(
        $curve1StartKey,
        $curve1Control1Key,
        $curve1Control2Key,
        $curve1EndKey,
        $curve2StartKey,
        $curve2Control1Key,
        $curve2Control2Key,
        $curve2EndKey,
        $prefix = false
    ) {
        $points = BezierToolbox::findCurveCurveIntersections(
            $this->loadPoint($curve1StartKey),
            $this->loadPoint($curve1Control1Key), $this->loadPoint($curve1Control2Key), $this->loadPoint($curve1EndKey),
            $this->loadPoint($curve2StartKey), $this->loadPoint($curve2Control1Key), $this->loadPoint($curve2Control2Key),
            $this->loadPoint($curve2EndKey)
        );
        if (is_array($points)) {
            $i = 1;
            foreach ($points as $point) {
                $this->addPoint("$prefix-$i", $point);
                $i++;
            }
        }
    }

    /**
     * Adds a (small) width dimension to the pattern
     *
     * @param string $fromId ID of the point that the dimension starts from
     * @param string $toId ID of the point that is the end of the dimension
     * @param float $y Y-coordinate where the dimension should be placed
     * @param string $text The text to put on the dimension label
     * @param array $pathAttributes Attributes for the path the label goes on
     * @param array $labelAttributes Attributes for the text of the label
     * @param array $leaderAttributes Attributes for the leader paths
     *
     */
    public function newWidthDimensionSm(
        $fromId,
        $toId,
        $y = false,
        $text = false,
        $pathAttributes=['class' => 'dimension dimension-sm'],
        $labelAttributes=['class' => 'dimension-label text-sm', 'dy' => -2],
        $leaderAttributes=['class' => 'dimension-leader']
    ) {
        $this->newWidthDimension($fromId,$toId,$y,$text,$pathAttributes,$labelAttributes);
    }

    /**
     * Adds a width dimension to the pattern
     *
     * @param string $fromId ID of the point that the dimension starts from
     * @param string $toId ID of the point that is the end of the dimension
     * @param float $y Y-coordinate where the dimension should be placed
     * @param string $text The text to put on the dimension label
     * @param array $pathAttributes Attributes for the path the label goes on
     * @param array $labelAttributes Attributes for the text of the label
     * @param array $leaderAttributes Attributes for the leader paths
     *
     */
    public function newWidthDimension(
        $fromId,
        $toId,
        $y = false,
        $text = false,
        $pathAttributes=['class' => 'dimension'],
        $labelAttributes=['class' => 'dimension-label', 'dy' => -2],
        $leaderAttributes=['class' => 'dimension-leader']
    ) {
        /** @var \Freesewing\Dimension $d */
        $d = new \Freesewing\Dimension();

        // Do we need a from leader?
        if($this->y($fromId) == $y || $y === false) { // Nope
            $pathFrom = $fromId;
        } else { // We do
            $i = $this->newId('.dw-');
            $this->newPoint($i, $this->x($fromId), $y);
            $pathFrom = $i;
            // Leader
            $fromLeader = new \Freesewing\Path;
            $fromLeader->setPath("M $fromId L $pathFrom");
            $fromLeader->setAttributes($leaderAttributes);
            $d->addLeader($fromLeader);
        }

        // Do we need a To leader?
        if($this->y($toId) == $y) { // Nope
            $pathTo = $toId;
        } else { // We do
            $i = $this->newId('.dw-');
            $this->newPoint($i, $this->x($toId), $this->y($pathFrom));
            $pathTo = $i;
            // Leader
            $toLeader = new \Freesewing\Path;
            $toLeader->setPath("M $toId L $pathTo");
            $toLeader->setAttributes($leaderAttributes);
            $d->addLeader($toLeader);
        }

        // Label (a TextOnPath object)
        $label = new \Freesewing\TextOnPath();

        // Path
        $path = new \Freesewing\Path();
        $path->setPath("M $pathFrom L $pathTo");
        $path->setAttributes($pathAttributes);

        // Text
        if($text === false) $text = $this->unit($this->distance($pathFrom, $pathTo));
        $label->setText($text);
        $label->setPath($path);
        $label->setAttributes($labelAttributes);
        $d->setLabel($label);
        $this->addDimension($d);
    }

    /**
     * Adds a (small) height dimension to the pattern
     *
     * @param string $fromId ID of the point that the dimension starts from
     * @param string $toId ID of the point that is the end of the dimension
     * @param float $x X-coordinate where the dimension should be placed
     * @param string $text The text to put on the dimension label
     * @param array $pathAttributes Attributes for the path the label goes on
     * @param array $labelAttributes Attributes for the text of the label
     * @param array $leaderAttributes Attributes for the leader paths
     *
     */
    public function newHeightDimensionSm(
        $fromId,
        $toId,
        $x = false,
        $text = false,
        $pathAttributes=['class' => 'dimension dimension-sm'],
        $labelAttributes=['class' => 'dimension-label text-sm', 'dy' => -2],
        $leaderAttributes=['class' => 'dimension-leader']
    ) {
        $this->newHeightDimension($fromId,$toId,$x,$text,$pathAttributes,$labelAttributes,$leaderAttributes);
    }

    /**
     * Adds a height dimension to the pattern
     *
     * @param string $fromId ID of the point that the dimension starts from
     * @param string $toId ID of the point that is the end of the dimension
     * @param float $x X-coordinate where the dimension should be placed
     * @param string $text The text to put on the dimension label
     * @param array $pathAttributes Attributes for the path the label goes on
     * @param array $labelAttributes Attributes for the text of the label
     * @param array $leaderAttributes Attributes for the leader paths
     *
     */
    public function newHeightDimension(
        $fromId,
        $toId,
        $x = false,
        $text = false,
        $pathAttributes=['class' => 'dimension'],
        $labelAttributes=['class' => 'dimension-label', 'dy' => -2],
        $leaderAttributes=['class' => 'dimension-leader']
    ) {
        /** @var \Freesewing\Dimension $d */
        $d = new \Freesewing\Dimension();

        // Do we need a from leader?
        if($this->x($fromId) == $x || $x === false) { // Nope
            $pathFrom = $fromId;
        } else { // We do
            $i = $this->newId('.dw-');
            $this->newPoint($i, $x, $this->y($fromId));
            $pathFrom = $i;
            // Leader
            $fromLeader = new \Freesewing\Path;
            $fromLeader->setPath("M $fromId L $i");
            $fromLeader->setAttributes($leaderAttributes);
            $d->addLeader($fromLeader);
        }

        // Do we need a To leader?
        if($this->x($toId) == $x) { // Nope
            $pathTo = $toId;
        } else { // We do
            $i = $this->newId('.dw-');
            $this->newPoint($i, $this->x($pathFrom), $this->y($toId));
            $pathTo = $i;
            // Leader
            $toLeader = new \Freesewing\Path;
            $toLeader->setPath("M $toId L $i");
            $toLeader->setAttributes($leaderAttributes);
            $d->addLeader($toLeader);
        }

        // Label (a TextOnPath object)
        $label = new \Freesewing\TextOnPath();

        // Path
        $path = new \Freesewing\Path();
        $path->setPath("M $pathFrom L $pathTo");
        $path->setAttributes($pathAttributes);

        // Text
        if($text === false) $text = $this->unit($this->distance($pathFrom, $pathTo));
        $label->setText($text);
        $label->setPath($path);
        $label->setAttributes($labelAttributes);
        $d->setLabel($label);

        $this->addDimension($d);
    }

    /**
     * Adds a (small) linear dimension to the pattern
     *
     * @param string $fromId ID of the point that the dimension starts from
     * @param string $toId ID of the point that is the end of the dimension
     * @param float $offset The amount to offset the dimension by
     * @param string $text The text to put on the dimension label
     * @param array $pathAttributes Attributes for the path the label goes on
     * @param array $labelAttributes Attributes for the text of the label
     * @param array $leaderAttributes Attributes for the leader paths
     *
     */
    public function newLinearDimensionSm(
        $fromId,
        $toId,
        $offset = 0,
        $text = false,
        $pathAttributes=['class' => 'dimension dimension-sm'],
        $labelAttributes=['class' => 'dimension-label text-sm', 'dy' => -2],
        $leaderAttributes=['class' => 'dimension-leader']
    ) {
        $this->newLinearDimension($fromId,$toId,$offset,$text,$pathAttributes,$labelAttributes,$leaderAttributes);
    }

    /**
     * Creates a linear dimension to the part
     *
     * @param string $fromId ID of the point that the dimension starts from
     * @param string $toId ID of the point that is the end of the dimension
     * @param float $offset The amount to offset the dimension by
     * @param string $text The text to put on the dimension label
     * @param array $pathAttributes Attributes for the path the label goes on
     * @param array $labelAttributes Attributes for the text of the label
     * @param array $leaderAttributes Attributes for the leader paths
     *
     */
    public function newLinearDimension(
        $fromId,
        $toId,
        $offset = 0,
        $text = false,
        $pathAttributes=['class' => 'dimension dimension-width'],
        $labelAttributes=['class' => 'dimension-label', 'dy' => -2],
        $leaderAttributes=['class' => 'dimension-leader']
    ) {
        /** @var \Freesewing\Dimension $d */
        $d = new \Freesewing\Dimension();

        if($offset != 0) { // We need leaders
            $angle = $this->angle($fromId,$toId)+90;
            foreach(['pathFrom' => $fromId, 'pathTo' => $toId] as $i => $point) {
                ${$i} = $this->newId('.dw-');
                $this->addPoint(${$i}, $this->shift($point, $angle, $offset));
                // Leader
                $leader = new \Freesewing\Path;
                $leader->setPath("M $point L ".${$i});
                $leader->setAttributes($leaderAttributes);
                $d->addLeader($leader);
            }
        } else { // No leaders
            $pathFrom = $fromId;
            $pathTo = $toId;
        }

        // Label (a TextOnPath object)
        $label = new \Freesewing\TextOnPath();

        // Path
        $path = new \Freesewing\Path();
        $path->setPath("M $pathFrom L $pathTo");
        $path->setAttributes($pathAttributes);

        // Text
        if($text === false) $text = $this->unit($this->distance($pathFrom, $pathTo));
        $label->setText($text);
        $label->setPath($path);
        $label->setAttributes($labelAttributes);
        $d->setLabel($label);

        $this->addDimension($d);
    }

    /**
     * Creates and adds a curved dimension to the part
     *
     * @param string $pathString The (unrendered) pathstring of the curve
     * @param float $offset X-coordinate where the dimension should be placed
     * @param string $text The text to put on the dimension label
     * @param array $pathAttributes Attributes for the path the label goes on
     * @param array $labelAttributes Attributes for the text of the label
     * @param array $leaderAttributes Attributes for the leader paths
     *
     */
    public function newCurvedDimension(
        $pathString,
        $offset = 0,
        $text = false,
        $pathAttributes=['class' => 'dimension dimension-width'],
        $labelAttributes=['class' => 'dimension-label', 'dy' => -2],
        $leaderAttributes=['class' => 'dimension-leader']
    ) {
        /** @var \Freesewing\Dimension $d */
        $d = new \Freesewing\Dimension();

        // Make pathstring into a path object
        $origPath = new \Freesewing\Path();
        $origPath->setPath($pathString);

        // Label (a TextOnPath object)
        $label = new \Freesewing\TextOnPath();

        // Path
        if($offset == 0) {
            $path = new \Freesewing\Path();
            $path->setPath($pathString);
            $path->setAttributes($pathAttributes);
        } else {
            $id = $this->newId('.dc-');
            $this->offsetPathString($id, $pathString, $offset, true, $pathAttributes);
            $path = $this->paths[$id];
        }

        // Text
        if($text === false) $text = $this->unit($this->pathLen($origPath));
        $label->setText($text);
        $label->setPath($path);
        $label->setAttributes($labelAttributes);
        $d->setLabel($label);

        // Leaders
        if($offset != 0) { // We need leaders

            // Start Leader
            $leader = new \Freesewing\Path;
            $leader->setPath('M '.$origPath->getStartPoint().' L '.$path->getStartPoint());
            $leader->setAttributes($leaderAttributes);
            $d->addLeader($leader);

            // End Leader
            $leader = new \Freesewing\Path;
            $leader->setPath('M '.$origPath->getEndPoint().' L '.$path->getEndPoint());
            $leader->setAttributes($leaderAttributes);
            $d->addLeader($leader);
        }

        $this->addDimension($d);
    }

    /**
     * Adds a grainline, by calling newLinearDimension() with specific attributes
     *
     * @param string $fromId ID of the point that the dimension starts from
     * @param string $toId ID of the point that is the end of the dimension
     * @param string $text The text to put on the grainline
     *
     */
    public function newGrainline($fromId, $toId, $text=' ')
    {
        $this->newLinearDimension($fromId, $toId, 0, $text, ['class' => 'grainline'], ['class' => 'text-lg text-center grainline', 'dy' => -2]);
    }

    /**
     * Adds a cut-on-fold line, by calling dl() with specific attributes
     *
     * @param string $fromId ID of the point that the dimension starts from
     * @param string $toId ID of the point that is the end of the dimension
     * @param string $text The text to put on the grainline
     *
     */
    public function newCutOnFold($fromId, $toId, $text, $offset=20)
    {
        // Add via points
        $angle = $this->angle($fromId, $toId)+90;

        $viaFrom = $this->newId('.cof');
        $this->addPoint($viaFrom, $this->shift($fromId,$angle,$offset));

        $viaTo = $this->newId('.cof');
        $this->addPoint($viaTo, $this->shift($toId,$angle,$offset));

        $this->newCurvedDimension("M $fromId L $viaFrom L $viaTo L $toId", 0, $text, ['class' => 'grainline'], ['class' => 'text-lg text-center grainline', 'dy' => -2]);
    }

    /**
     * Adds a dimension to $this->dimensions.
     *
     * This takes a pre-created dimension object
     * and adds it to the dimensions array with key $key.
     *
     * @param \Freesewing\Dimension   $dimension The dimension object
     */
    public function addDimension($dimension)
    {
        $this->dimensions[] = $dimension;
    }


    /**
     * Adds notches to point IDs passed in an array
     *
     * This adds a notch snippet to all point IDs in the array passed to it
     *
     * @param array $points Array of point IDs
     * @return void
     */
    public function notch($points)
    {
        foreach($points as $i) $this->newSnippet($this->newId('notch'), 'notch', $i); 
    }
}
