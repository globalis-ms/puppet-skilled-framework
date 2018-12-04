<?php
namespace Globalis\PuppetSkilled\Tests\Database\Magic\Relations;

use Mockery as m;
use Globalis\PuppetSkilled\Database\Magic\Relations\MorphTo;

class MorphToTest extends \PHPUnit\Framework\TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testLookupDictionaryIsProperlyConstructed()
    {
        $relation = $this->getRelation();
        $relation->addEagerConstraints([
            $one = (object) ['morph_type' => 'morph_type_1', 'foreign_key' => 'foreign_key_1'],
            $two = (object) ['morph_type' => 'morph_type_1', 'foreign_key' => 'foreign_key_1'],
            $three = (object) ['morph_type' => 'morph_type_2', 'foreign_key' => 'foreign_key_2'],
        ]);

        $dictionary = $relation->getDictionary();

        $this->assertEquals([
            'morph_type_1' => [
                'foreign_key_1' => [
                    $one,
                    $two,
                ],
            ],
            'morph_type_2' => [
                'foreign_key_2' => [
                    $three,
                ],
            ],
        ], $dictionary);
    }

    public function testAssociateMethodSetsForeignKeyAndTypeOnModel()
    {
        $parent = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('foreign.value');

        $relation = $this->getRelationAssociate($parent);

        $associate = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $associate->shouldReceive('getKey')->once()->andReturn(1);
        $associate->shouldReceive('getMorphClass')->once()->andReturn('Model');

        $parent->shouldReceive('setAttribute')->once()->with('foreign_key', 1);
        $parent->shouldReceive('setAttribute')->once()->with('morph_type', 'Model');
        $parent->shouldReceive('setRelation')->once()->with('relation', $associate);

        $relation->associate($associate);
    }

    public function testDissociateMethodDeletesUnsetsKeyAndTypeOnModel()
    {
        $parent = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('foreign.value');

        $relation = $this->getRelation($parent);

        $parent->shouldReceive('setAttribute')->once()->with('foreign_key', null);
        $parent->shouldReceive('setAttribute')->once()->with('morph_type', null);
        $parent->shouldReceive('setRelation')->once()->with('relation', null);

        $relation->dissociate();
    }

    protected function getRelationAssociate($parent)
    {
        $builder = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $builder->shouldReceive('where')->with('relation.id', '=', 'foreign.value');
        $related = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $related->shouldReceive('getKey')->andReturn(1);
        $related->shouldReceive('getTable')->andReturn('relation');
        $builder->shouldReceive('getModel')->andReturn($related);

        return new MorphTo($builder, $parent, 'foreign_key', 'id', 'morph_type', 'relation');
    }

    public function getRelation($parent = null, $builder = null)
    {
        $builder = $builder ?: m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $builder->shouldReceive('where')->with('relation.id', '=', 'foreign.value');
        $related = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $related->shouldReceive('getKeyName')->andReturn('id');
        $related->shouldReceive('getTable')->andReturn('relation');
        $builder->shouldReceive('getModel')->andReturn($related);
        $parent = $parent ?: new MorphToModelStub;
        $morphTo = m::mock('Globalis\PuppetSkilled\Database\Magic\Relations\MorphTo[createModelByType]', [$builder, $parent, 'foreign_key', 'id', 'morph_type', 'relation']);

        return $morphTo;
    }
}

class MorphToModelStub extends \Globalis\PuppetSkilled\Database\Magic\Model
{
    public $foreign_key = 'foreign.value';
}
