<?php
namespace Globalis\PuppetSkilled\Tests\Database\Magic;

use Carbon\Carbon;
use Mockery as m;
use Globalis\PuppetSkilled\Database\Magic\Model;
use Globalis\PuppetSkilled\Database\Magic\Builder;
use Globalis\PuppetSkilled\Database\Magic\Relations\Relation;
use DateTime;
use DateTimeInterface;
use DateTimeImmutable;
use ReflectionClass;

class ModelTest extends \PHPUnit\Framework\TestCase
{
    public function tearDown()
    {
        m::close();

        Model::unsetEventDispatcher();
        Carbon::resetToStringFormat();
    }

    public function testAttributeManipulation()
    {
        $model = new ModelStub;
        $model->name = 'foo';
        $this->assertEquals('foo', $model->name);
        $this->assertTrue(isset($model->name));
        unset($model->name);
        $this->assertFalse(isset($model->name));

        // test mutation
        $model->list_items = ['name' => 'taylor'];
        $this->assertEquals(['name' => 'taylor'], $model->list_items);
        $attributes = $model->getAttributes();
        $this->assertEquals(json_encode(['name' => 'taylor']), $attributes['list_items']);
    }

    public function testDirtyAttributes()
    {
        $model = new ModelStub(['foo' => '1', 'bar' => 2, 'baz' => 3]);
        $model->syncOriginal();
        $model->foo = 1;
        $model->bar = 20;
        $model->baz = 30;
        $this->assertTrue($model->isDirty());
        $this->assertFalse($model->isDirty('foo'));
        $this->assertTrue($model->isDirty('bar'));
        $this->assertTrue($model->isDirty('foo', 'bar'));
        $this->assertTrue($model->isDirty(['foo', 'bar']));
    }

    public function testCleanAttributes()
    {
        $model = new ModelStub(['foo' => '1', 'bar' => 2, 'baz' => 3]);
        $model->syncOriginal();
        $model->foo = 1;
        $model->bar = 20;
        $model->baz = 30;

        $this->assertFalse($model->isClean());
        $this->assertTrue($model->isClean('foo'));
        $this->assertFalse($model->isClean('bar'));
        $this->assertFalse($model->isClean('foo', 'bar'));
        $this->assertFalse($model->isClean(['foo', 'bar']));
    }

    public function testCalculatedAttributes()
    {
        $model = new ModelStub;
        $model->password = 'secret';
        $attributes = $model->getAttributes();

        // ensure password attribute was not set to null
        $this->assertArrayNotHasKey('password', $attributes);
        $this->assertEquals('******', $model->password);

        $hash = 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4';

        $this->assertEquals($hash, $attributes['password_hash']);
        $this->assertEquals($hash, $model->password_hash);
    }

    public function testNewInstanceReturnsNewInstanceWithAttributesSet()
    {
        $model = new ModelStub;
        $instance = $model->newInstance(['name' => 'taylor']);
        $this->assertInstanceOf('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub', $instance);
        $this->assertEquals('taylor', $instance->name);
    }

    public function testHydrateCreatesCollectionOfModels()
    {
        $data = [['name' => 'Taylor'], ['name' => 'Otwell']];
        $collection = ModelStub::hydrate($data, 'foo_connection');

        $this->assertCount(2, $collection);
        $this->assertInstanceOf('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub', $collection[0]);
        $this->assertInstanceOf('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub', $collection[1]);
        $this->assertEquals($collection[0]->getAttributes(), $collection[0]->getOriginal());
        $this->assertEquals($collection[1]->getAttributes(), $collection[1]->getOriginal());
        $this->assertEquals('Taylor', $collection[0]->name);
        $this->assertEquals('Otwell', $collection[1]->name);
    }

    public function testCreateMethodSavesNewModel()
    {
        $_SERVER['__eloquent.saved'] = false;
        $model = ModelSaveStub::create(['name' => 'taylor']);
        $this->assertTrue($_SERVER['__eloquent.saved']);
        $this->assertEquals('taylor', $model->name);
    }

    public function testForceCreateMethodSavesNewModelWithGuardedAttributes()
    {
        $_SERVER['__eloquent.saved'] = false;
        $model = ModelSaveStub::forceCreate(['id' => 21]);
        $this->assertTrue($_SERVER['__eloquent.saved']);
        $this->assertEquals(21, $model->id);
    }

    public function testDestroyMethodCallsQueryBuilderCorrectly()
    {
        $result = MagicModelDestroyStub::destroy(1, 2, 3);
    }

    public function testWithMethodCallsQueryBuilderCorrectly()
    {
        $result = ModelWithStub::with('foo', 'bar');
        $this->assertEquals('foo', $result);
    }

    public function testWithoutMethodRemovesEagerLoadedRelationshipCorrectly()
    {
        $model = new ModelWithoutRelationStub;
        $instance = $model->newInstance()->newQuery()->without('foo');
        $this->assertEmpty($instance->getEagerLoads());
    }

    public function testEagerLoadingWithColumns()
    {
        $model = new ModelWithoutRelationStub;
        $instance = $model->newInstance()->newQuery()->with('foo:bar,baz', 'hadi');
        $builder = m::mock(Builder::class);
        $builder->shouldReceive('select')->once()->with(['bar', 'baz']);
        $this->assertNotNull($instance->getEagerLoads()['hadi']);
        $this->assertNotNull($instance->getEagerLoads()['foo']);
        $closure = $instance->getEagerLoads()['foo'];
        $closure($builder);
    }

    public function testWithMethodCallsQueryBuilderCorrectlyWithArray()
    {
        $result = ModelWithStub::with(['foo', 'bar']);
        $this->assertEquals('foo', $result);
    }

    public function testUpdateProcess()
    {
        $model = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub')->setMethods(['newQueryWithoutScopes', 'updateTimestamps'])->getMock();
        $query = m::mock('\Globalis\PuppetSkilled\Database\Magic\Builder');
        $query->shouldReceive('where')->once()->with('id', '=', 1);
        $query->shouldReceive('update')->once()->with(['name' => 'taylor'])->andReturn(1);
        $model->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));
        $model->expects($this->once())->method('updateTimestamps');
        $model->setEventDispatcher($events = m::mock('\Globalis\PuppetSkilled\Event\Dispatcher'));
        $events->shouldReceive('until')->once()->with('puppet.saving: '.get_class($model), $model)->andReturn(true);
        $events->shouldReceive('until')->once()->with('puppet.updating: '.get_class($model), $model)->andReturn(true);
        $events->shouldReceive('fire')->once()->with('puppet.updated: '.get_class($model), $model)->andReturn(true);
        $events->shouldReceive('fire')->once()->with('puppet.saved: '.get_class($model), $model)->andReturn(true);

        $model->id = 1;
        $model->foo = 'bar';
        // make sure foo isn't synced so we can test that dirty attributes only are updated
        $model->syncOriginal();
        $model->name = 'taylor';
        $model->exists = true;
        $this->assertTrue($model->save());
    }

    public function testUpdateProcessDoesntOverrideTimestamps()
    {
        $model = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub')->setMethods(['newQueryWithoutScopes'])->getMock();
        $query = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $query->shouldReceive('where')->once()->with('id', '=', 1);
        $query->shouldReceive('update')->once()->with(['created_at' => 'foo', 'updated_at' => 'bar'])->andReturn(1);
        $model->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));
        $model->setEventDispatcher($events = m::mock('Globalis\PuppetSkilled\Event\Dispatcher'));
        $events->shouldReceive('until');
        $events->shouldReceive('fire');

        $model->id = 1;
        $model->syncOriginal();
        $model->created_at = 'foo';
        $model->updated_at = 'bar';
        $model->exists = true;
        $this->assertTrue($model->save());
    }

    public function testSaveIsCancelledIfSavingEventReturnsFalse()
    {
        $model = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub')->setMethods(['newQueryWithoutScopes'])->getMock();
        $query = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $model->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));
        $model->setEventDispatcher($events = m::mock('Globalis\PuppetSkilled\Event\Dispatcher'));
        $events->shouldReceive('until')->once()->with('puppet.saving: '.get_class($model), $model)->andReturn(false);
        $model->exists = true;

        $this->assertFalse($model->save());
    }

    public function testUpdateIsCancelledIfUpdatingEventReturnsFalse()
    {
        $model = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub')->setMethods(['newQueryWithoutScopes'])->getMock();
        $query = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $model->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));
        $model->setEventDispatcher($events = m::mock('Globalis\PuppetSkilled\Event\Dispatcher'));
        $events->shouldReceive('until')->once()->with('puppet.saving: '.get_class($model), $model)->andReturn(true);
        $events->shouldReceive('until')->once()->with('puppet.updating: '.get_class($model), $model)->andReturn(false);
        $model->exists = true;
        $model->foo = 'bar';

        $this->assertFalse($model->save());
    }

    public function testUpdateProcessWithoutTimestamps()
    {
        $model = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub')->setMethods(['newQueryWithoutScopes', 'updateTimestamps', 'fireModelEvent'])->getMock();
        $model->timestamps = false;
        $query = m::mock('\Globalis\PuppetSkilled\Database\Magic\Builder');
        $query->shouldReceive('where')->once()->with('id', '=', 1);
        $query->shouldReceive('update')->once()->with(['name' => 'taylor'])->andReturn(1);
        $model->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));
        $model->expects($this->never())->method('updateTimestamps');
        $model->expects($this->any())->method('fireModelEvent')->will($this->returnValue(true));

        $model->id = 1;
        $model->syncOriginal();
        $model->name = 'taylor';
        $model->exists = true;
        $this->assertTrue($model->save());
    }

    public function testUpdateUsesOldPrimaryKey()
    {
        $model = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub')->setMethods(['newQueryWithoutScopes', 'updateTimestamps'])->getMock();
        $query = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $query->shouldReceive('where')->once()->with('id', '=', 1);
        $query->shouldReceive('update')->once()->with(['id' => 2, 'foo' => 'bar'])->andReturn(1);
        $model->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));
        $model->expects($this->once())->method('updateTimestamps');
        $model->setEventDispatcher($events = m::mock('Globalis\PuppetSkilled\Event\Dispatcher'));
        $events->shouldReceive('until')->once()->with('puppet.saving: '.get_class($model), $model)->andReturn(true);
        $events->shouldReceive('until')->once()->with('puppet.updating: '.get_class($model), $model)->andReturn(true);
        $events->shouldReceive('fire')->once()->with('puppet.updated: '.get_class($model), $model)->andReturn(true);
        $events->shouldReceive('fire')->once()->with('puppet.saved: '.get_class($model), $model)->andReturn(true);

        $model->id = 1;
        $model->syncOriginal();
        $model->id = 2;
        $model->foo = 'bar';
        $model->exists = true;

        $this->assertTrue($model->save());
    }

    public function testTimestampsAreReturnedAsObjects()
    {
        $model = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\DateModelStub')->setMethods(['getDateFormat'])->getMock();
        $model->expects($this->any())->method('getDateFormat')->will($this->returnValue('Y-m-d'));
        $model->setRawAttributes([
            'created_at' => '2012-12-04',
            'updated_at' => '2012-12-05',
        ]);

        $this->assertInstanceOf('Carbon\Carbon', $model->created_at);
        $this->assertInstanceOf('Carbon\Carbon', $model->updated_at);
    }

    public function testTimestampsAreReturnedAsObjectsFromPlainDatesAndTimestamps()
    {
        $model = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\DateModelStub')->setMethods(['getDateFormat'])->getMock();
        $model->expects($this->any())->method('getDateFormat')->will($this->returnValue('Y-m-d H:i:s'));
        $model->setRawAttributes([
            'created_at' => '2012-12-04',
            'updated_at' => time(),
        ]);

        $this->assertInstanceOf('Carbon\Carbon', $model->created_at);
        $this->assertInstanceOf('Carbon\Carbon', $model->updated_at);
    }

    public function testTimestampsAreReturnedAsObjectsOnCreate()
    {
        $timestamps = [
            'created_at' =>Carbon::now(),
            'updated_at' =>Carbon::now(),
        ];
        $model = new DateModelStub;
        $instance = $model->newInstance($timestamps);
        $this->assertInstanceOf('Carbon\Carbon', $instance->updated_at);
        $this->assertInstanceOf('Carbon\Carbon', $instance->created_at);
    }

    public function testDateTimeAttributesReturnNullIfSetToNull()
    {
        $timestamps = [
            'created_at' =>Carbon::now(),
            'updated_at' =>Carbon::now(),
        ];
        $model = new DateModelStub;
        $instance = $model->newInstance($timestamps);

        $instance->created_at = null;
        $this->assertNull($instance->created_at);
    }

    public function testTimestampsAreCreatedFromStringsAndIntegers()
    {
        $model = new DateModelStub;
        $model->created_at = '2013-05-22 00:00:00';
        $this->assertInstanceOf('Carbon\Carbon', $model->created_at);

        $model = new DateModelStub;
        $model->created_at = time();
        $this->assertInstanceOf('Carbon\Carbon', $model->created_at);

        $model = new DateModelStub;
        $model->created_at = 0;
        $this->assertInstanceOf('Carbon\Carbon', $model->created_at);

        $model = new DateModelStub;
        $model->created_at = '2012-01-01';
        $this->assertInstanceOf('Carbon\Carbon', $model->created_at);
    }

    public function testFromDateTime()
    {
        $model = new ModelStub();

        $value =Carbon::parse('2015-04-17 22:59:01');
        $this->assertEquals('2015-04-17 22:59:01.000000', $model->fromDateTime($value));

        $value = new DateTime('2015-04-17 22:59:01');
        $this->assertInstanceOf(DateTime::class, $value);
        $this->assertInstanceOf(DateTimeInterface::class, $value);
        $this->assertEquals('2015-04-17 22:59:01.000000', $model->fromDateTime($value));

        $value = new DateTimeImmutable('2015-04-17 22:59:01');
        $this->assertInstanceOf(DateTimeImmutable::class, $value);
        $this->assertInstanceOf(DateTimeInterface::class, $value);
        $this->assertEquals('2015-04-17 22:59:01.000000', $model->fromDateTime($value));

        $value = '2015-04-17 22:59:01';
        $this->assertEquals('2015-04-17 22:59:01.000000', $model->fromDateTime($value));

        $value = '2015-04-17';
        $this->assertEquals('2015-04-17 00:00:00.000000', $model->fromDateTime($value));

        $value = '2015-4-17';
        $this->assertEquals('2015-04-17 00:00:00.000000', $model->fromDateTime($value));

        $value = '1429311541';
        $this->assertEquals('2015-04-17 22:59:01.000000', $model->fromDateTime($value));
    }

    public function testInsertProcess()
    {
        $model = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub')->setMethods(['newQueryWithoutScopes', 'updateTimestamps', 'refresh'])->getMock();
        $query = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $query->shouldReceive('insertGetId')->once()->with(['name' => 'taylor'], 'id')->andReturn(1);
        $model->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));
        $model->expects($this->once())->method('updateTimestamps');

        $model->setEventDispatcher($events = m::mock('Globalis\PuppetSkilled\Event\Dispatcher'));
        $events->shouldReceive('until')->once()->with('puppet.saving: '.get_class($model), $model)->andReturn(true);
        $events->shouldReceive('until')->once()->with('puppet.creating: '.get_class($model), $model)->andReturn(true);
        $events->shouldReceive('fire')->once()->with('puppet.created: '.get_class($model), $model);
        $events->shouldReceive('fire')->once()->with('puppet.saved: '.get_class($model), $model);

        $model->name = 'taylor';
        $model->exists = false;
        $this->assertTrue($model->save());
        $this->assertEquals(1, $model->id);
        $this->assertTrue($model->exists);

        $model = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub')->setMethods(['newQueryWithoutScopes', 'updateTimestamps', 'refresh'])->getMock();
        $query = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $query->shouldReceive('insert')->once()->with(['name' => 'taylor']);
        $model->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));
        $model->expects($this->once())->method('updateTimestamps');
        $model->setIncrementing(false);

        $model->setEventDispatcher($events = m::mock('Globalis\PuppetSkilled\Event\Dispatcher'));
        $events->shouldReceive('until')->once()->with('puppet.saving: '.get_class($model), $model)->andReturn(true);
        $events->shouldReceive('until')->once()->with('puppet.creating: '.get_class($model), $model)->andReturn(true);
        $events->shouldReceive('fire')->once()->with('puppet.created: '.get_class($model), $model);
        $events->shouldReceive('fire')->once()->with('puppet.saved: '.get_class($model), $model);

        $model->name = 'taylor';
        $model->exists = false;
        $this->assertTrue($model->save());
        $this->assertNull($model->id);
        $this->assertTrue($model->exists);
    }

    public function testInsertIsCancelledIfCreatingEventReturnsFalse()
    {
        $model = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub')->setMethods(['newQueryWithoutScopes'])->getMock();
        $query = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $model->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));
        $model->setEventDispatcher($events = m::mock('Globalis\PuppetSkilled\Event\Dispatcher'));
        $events->shouldReceive('until')->once()->with('puppet.saving: '.get_class($model), $model)->andReturn(true);
        $events->shouldReceive('until')->once()->with('puppet.creating: '.get_class($model), $model)->andReturn(false);

        $this->assertFalse($model->save());
        $this->assertFalse($model->exists);
    }

    public function testDeleteProperlyDeletesModel()
    {
        $model = $this->getMockBuilder('\Globalis\PuppetSkilled\Database\Magic\Model')->setMethods(['newQueryWithoutScopes', 'updateTimestamps', 'touchOwners'])->getMock();
        $query = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $query->shouldReceive('where')->once()->with('id', '=', 1)->andReturn($query);
        $query->shouldReceive('delete')->once();
        $model->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));
        $model->expects($this->once())->method('touchOwners');
        $model->exists = true;
        $model->id = 1;
        $model->delete();
    }

    public function testPushNoRelations()
    {
        $model = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub')->setMethods(['newQueryWithoutScopes', 'updateTimestamps', 'refresh'])->getMock();
        $query = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $query->shouldReceive('insertGetId')->once()->with(['name' => 'taylor'], 'id')->andReturn(1);
        $model->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));
        $model->expects($this->once())->method('updateTimestamps');

        $model->name = 'taylor';
        $model->exists = false;

        $this->assertTrue($model->push());
        $this->assertEquals(1, $model->id);
        $this->assertTrue($model->exists);
    }

    public function testPushEmptyOneRelation()
    {
        $model = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub')->setMethods(['newQueryWithoutScopes', 'updateTimestamps', 'refresh'])->getMock();
        $query = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $query->shouldReceive('insertGetId')->once()->with(['name' => 'taylor'], 'id')->andReturn(1);
        $model->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));
        $model->expects($this->once())->method('updateTimestamps');

        $model->name = 'taylor';
        $model->exists = false;
        $model->setRelation('relationOne', null);

        $this->assertTrue($model->push());
        $this->assertEquals(1, $model->id);
        $this->assertTrue($model->exists);
        $this->assertNull($model->relationOne);
    }

    public function testPushOneRelation()
    {
        $related1 = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub')->setMethods(['newQueryWithoutScopes', 'updateTimestamps', 'refresh'])->getMock();
        $query = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $query->shouldReceive('insertGetId')->once()->with(['name' => 'related1'], 'id')->andReturn(2);
        $related1->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));
        $related1->expects($this->once())->method('updateTimestamps');
        $related1->name = 'related1';
        $related1->exists = false;

        $model = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub')->setMethods(['newQueryWithoutScopes', 'updateTimestamps', 'refresh'])->getMock();
        $query = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $query->shouldReceive('insertGetId')->once()->with(['name' => 'taylor'], 'id')->andReturn(1);
        $model->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));
        $model->expects($this->once())->method('updateTimestamps');

        $model->name = 'taylor';
        $model->exists = false;
        $model->setRelation('relationOne', $related1);

        $this->assertTrue($model->push());
        $this->assertEquals(1, $model->id);
        $this->assertTrue($model->exists);
        $this->assertEquals(2, $model->relationOne->id);
        $this->assertTrue($model->relationOne->exists);
        $this->assertEquals(2, $related1->id);
        $this->assertTrue($related1->exists);
    }

    public function testPushEmptyManyRelation()
    {
        $model = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub')->setMethods(['newQueryWithoutScopes', 'updateTimestamps', 'refresh'])->getMock();
        $query = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $query->shouldReceive('insertGetId')->once()->with(['name' => 'taylor'], 'id')->andReturn(1);
        $model->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));
        $model->expects($this->once())->method('updateTimestamps');

        $model->name = 'taylor';
        $model->exists = false;
        $model->setRelation('relationMany', []);

        $this->assertTrue($model->push());
        $this->assertEquals(1, $model->id);
        $this->assertTrue($model->exists);
        $this->assertEquals(0, count($model->relationMany));
    }

    public function testPushManyRelation()
    {
        $related1 = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub')->setMethods(['newQueryWithoutScopes', 'updateTimestamps', 'refresh'])->getMock();
        $query = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $query->shouldReceive('insertGetId')->once()->with(['name' => 'related1'], 'id')->andReturn(2);
        $related1->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));
        $related1->expects($this->once())->method('updateTimestamps');
        $related1->name = 'related1';
        $related1->exists = false;

        $related2 = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub')->setMethods(['newQueryWithoutScopes', 'updateTimestamps', 'refresh'])->getMock();
        $query = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $query->shouldReceive('insertGetId')->once()->with(['name' => 'related2'], 'id')->andReturn(3);
        $related2->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));
        $related2->expects($this->once())->method('updateTimestamps');
        $related2->name = 'related2';
        $related2->exists = false;

        $model = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub')->setMethods(['newQueryWithoutScopes', 'updateTimestamps', 'refresh'])->getMock();
        $query = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $query->shouldReceive('insertGetId')->once()->with(['name' => 'taylor'], 'id')->andReturn(1);
        $model->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));
        $model->expects($this->once())->method('updateTimestamps');

        $model->name = 'taylor';
        $model->exists = false;
        $model->setRelation('relationMany', [$related1, $related2]);

        $this->assertTrue($model->push());
        $this->assertEquals(1, $model->id);
        $this->assertTrue($model->exists);
        $this->assertEquals(2, count($model->relationMany));
        /* @TODO pluck function
        $this->assertEquals([2, 3], $model->relationMany->pluck('id')->all());*/
    }

    public function testNewQueryReturnsEloquentQueryBuilder()
    {
        $model = new ModelStub;
        $builder = $model->newQuery();
        $this->assertInstanceOf('Globalis\PuppetSkilled\Database\Magic\Builder', $builder);
    }

    public function testGetAndSetTableOperations()
    {
        $model = new ModelStub;
        $this->assertEquals('stub', $model->getTable());
        $model->setTable('foo');
        $this->assertEquals('foo', $model->getTable());
    }

    public function testGetKeyReturnsValueOfPrimaryKey()
    {
        $model = new ModelStub;
        $model->id = 1;
        $this->assertEquals(1, $model->getKey());
        $this->assertEquals('id', $model->getKeyName());
    }
    /* @TODO add connection manager
    public function testConnectionManagement()
    {
        ModelStub::setConnectionResolver($resolver = m::mock('Illuminate\Database\ConnectionResolverInterface'));
        $model = m::mock('Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub[getConnectionName,connection]');

        $retval = $model->setConnection('foo');
        $this->assertEquals($retval, $model);
        $this->assertEquals('foo', $model->connection);

        $model->shouldReceive('getConnectionName')->once()->andReturn('somethingElse');
        $resolver->shouldReceive('connection')->once()->with('somethingElse')->andReturn('bar');

        $this->assertEquals('bar', $model->getConnection());
    }*/

    public function testToArray()
    {
        $model = new ModelStub;
        $model->name = 'foo';
        $model->age = null;
        $model->password = 'password1';
        $model->setHidden(['password']);
        $model->setRelation('names', [new ModelStub(['bar' => 'baz']), new ModelStub(['bam' => 'boom'])]);
        $model->setRelation('partner', new ModelStub(['name' => 'abby']));
        $model->setRelation('group', null);
        $model->setRelation('multi', []);
        $array = $model->toArray();

        $this->assertInternalType('array', $array);
        $this->assertEquals('foo', $array['name']);
        $this->assertEquals('baz', $array['names'][0]['bar']);
        $this->assertEquals('boom', $array['names'][1]['bam']);
        $this->assertEquals('abby', $array['partner']['name']);
        $this->assertNull($array['group']);
        $this->assertEquals([], $array['multi']);
        $this->assertFalse(isset($array['password']));

        $model->setAppends(['appendable']);
        $array = $model->toArray();
        $this->assertEquals('appended', $array['appendable']);
    }

    public function testVisibleCreatesArrayWhitelist()
    {
        $model = new ModelStub;
        $model->setVisible(['name']);
        $model->name = 'Taylor';
        $model->age = 26;
        $array = $model->toArray();

        $this->assertEquals(['name' => 'Taylor'], $array);
    }

    public function testHiddenCanAlsoExcludeRelationships()
    {
        $model = new ModelStub;
        $model->name = 'Taylor';
        $model->setRelation('foo', ['bar']);
        $model->setHidden(['foo', 'list_items', 'password']);
        $array = $model->toArray();

        $this->assertEquals(['name' => 'Taylor'], $array);
    }

    public function testGetArrayableRelationsFunctionExcludeHiddenRelationships()
    {
        $model = new ModelStub;

        $class = new ReflectionClass($model);
        $method = $class->getMethod('getArrayableRelations');
        $method->setAccessible(true);

        $model->setRelation('foo', ['bar']);
        $model->setRelation('bam', ['boom']);
        $model->setHidden(['foo']);

        $array = $method->invokeArgs($model, []);

        $this->assertSame(['bam' => ['boom']], $array);
    }

    public function testToArraySnakeAttributes()
    {
        $model = new ModelStub;
        $model->setRelation('namesList', [new ModelStub(['bar' => 'baz']), new ModelStub(['bam' => 'boom'])]);
        $array = $model->toArray();

        $this->assertEquals('baz', $array['names_list'][0]['bar']);
        $this->assertEquals('boom', $array['names_list'][1]['bam']);

        $model = new ModelCamelStub;
        $model->setRelation('namesList', [new ModelStub(['bar' => 'baz']), new ModelStub(['bam' => 'boom'])]);
        $array = $model->toArray();

        $this->assertEquals('baz', $array['namesList'][0]['bar']);
        $this->assertEquals('boom', $array['namesList'][1]['bam']);
    }

    public function testToArrayUsesMutators()
    {
        $model = new ModelStub;
        $model->list_items = [1, 2, 3];
        $array = $model->toArray();

        $this->assertEquals([1, 2, 3], $array['list_items']);
    }

    public function testHidden()
    {
        $model = new ModelStub(['name' => 'foo', 'age' => 'bar', 'id' => 'baz']);
        $model->setHidden(['age', 'id']);
        $array = $model->toArray();
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('age', $array);
    }

    public function testVisible()
    {
        $model = new ModelStub(['name' => 'foo', 'age' => 'bar', 'id' => 'baz']);
        $model->setVisible(['name', 'id']);
        $array = $model->toArray();
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('age', $array);
    }

    public function testDynamicHidden()
    {
        $model = new ModelDynamicHiddenStub(['name' => 'foo', 'age' => 'bar', 'id' => 'baz']);
        $array = $model->toArray();
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('age', $array);
    }

    public function testWithHidden()
    {
        $model = new ModelStub(['name' => 'foo', 'age' => 'bar', 'id' => 'baz']);
        $model->setHidden(['age', 'id']);
        $model->makeVisible('age');
        $array = $model->toArray();
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('age', $array);
        $this->assertArrayNotHasKey('id', $array);
    }

    public function testMakeHidden()
    {
        $model = new ModelStub(['name' => 'foo', 'age' => 'bar', 'address' => 'foobar', 'id' => 'baz']);
        $array = $model->toArray();
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('age', $array);
        $this->assertArrayHasKey('address', $array);
        $this->assertArrayHasKey('id', $array);

        $array = $model->makeHidden('address')->toArray();
        $this->assertArrayNotHasKey('address', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('age', $array);
        $this->assertArrayHasKey('id', $array);

        $array = $model->makeHidden(['name', 'age'])->toArray();
        $this->assertArrayNotHasKey('name', $array);
        $this->assertArrayNotHasKey('age', $array);
        $this->assertArrayNotHasKey('address', $array);
        $this->assertArrayHasKey('id', $array);
    }

    public function testDynamicVisible()
    {
        $model = new ModelDynamicVisibleStub(['name' => 'foo', 'age' => 'bar', 'id' => 'baz']);
        $array = $model->toArray();
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('age', $array);
    }

    public function testFillable()
    {
        $model = new ModelStub;
        $model->fillable(['name', 'age']);
        $model->fill(['name' => 'foo', 'age' => 'bar']);
        $this->assertEquals('foo', $model->name);
        $this->assertEquals('bar', $model->age);
    }

    public function testForceFillMethodFillsGuardedAttributes()
    {
        $model = (new ModelSaveStub)->forceFill(['id' => 21]);
        $this->assertEquals(21, $model->id);
    }

    public function testFillingJSONAttributes()
    {
        $model = new ModelStub;
        $model->fillable(['meta->name', 'meta->price', 'meta->size->width']);
        $model->fill(['meta->name' => 'foo', 'meta->price' => 'bar', 'meta->size->width' => 'baz']);
        $this->assertEquals(
            ['meta' => json_encode(['name' => 'foo', 'price' => 'bar', 'size' => ['width' => 'baz']])],
            $model->toArray()
        );

        $model = new ModelStub(['meta' => json_encode(['name' => 'Taylor'])]);
        $model->fillable(['meta->name', 'meta->price', 'meta->size->width']);
        $model->fill(['meta->name' => 'foo', 'meta->price' => 'bar', 'meta->size->width' => 'baz']);
        $this->assertEquals(
            ['meta' => json_encode(['name' => 'foo', 'price' => 'bar', 'size' => ['width' => 'baz']])],
            $model->toArray()
        );
    }

    public function testUnguardAllowsAnythingToBeSet()
    {
        $model = new ModelStub;
        ModelStub::unguard();
        $model->guard(['*']);
        $model->fill(['name' => 'foo', 'age' => 'bar']);
        $this->assertEquals('foo', $model->name);
        $this->assertEquals('bar', $model->age);
        ModelStub::unguard(false);
    }

    public function testUnderscorePropertiesAreNotFilled()
    {
        $model = new ModelStub;
        $model->fill(['_method' => 'PUT']);
        $this->assertEquals([], $model->getAttributes());
    }

    public function testGuarded()
    {
        $model = new ModelStub;
        $model->guard(['name', 'age']);
        $model->fill(['name' => 'foo', 'age' => 'bar', 'foo' => 'bar']);
        $this->assertFalse(isset($model->name));
        $this->assertFalse(isset($model->age));
        $this->assertEquals('bar', $model->foo);
    }

    public function testFillableOverridesGuarded()
    {
        $model = new ModelStub;
        $model->guard(['name', 'age']);
        $model->fillable(['age', 'foo']);
        $model->fill(['name' => 'foo', 'age' => 'bar', 'foo' => 'bar']);
        $this->assertFalse(isset($model->name));
        $this->assertEquals('bar', $model->age);
        $this->assertEquals('bar', $model->foo);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGlobalGuarded()
    {
        $model = new ModelStub;
        $model->guard(['*']);
        $model->fill(['name' => 'foo', 'age' => 'bar', 'votes' => 'baz']);
    }

    public function testUnguardedRunsCallbackWhileBeingUnguarded()
    {
        $model = Model::unguarded(function () {
            return (new ModelStub)->guard(['*'])->fill(['name' => 'Taylor']);
        });
        $this->assertEquals('Taylor', $model->name);
        $this->assertFalse(Model::isUnguarded());
    }

    public function testUnguardedCallDoesNotChangeUnguardedState()
    {
        Model::unguard();
        $model = Model::unguarded(function () {
            return (new ModelStub)->guard(['*'])->fill(['name' => 'Taylor']);
        });
        $this->assertEquals('Taylor', $model->name);
        $this->assertTrue(Model::isUnguarded());
        Model::reguard();
    }

    public function testUnguardedCallDoesNotChangeUnguardedStateOnException()
    {
        try {
            Model::unguarded(function () {
                throw new \Exception;
            });
        } catch (\Exception $e) {
            // ignore the exception
        }
        $this->assertFalse(Model::isUnguarded());
    }

    public function testHasOneCreatesProperRelation()
    {
        $model = new ModelStub;
        $relation = $model->hasOne('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelSaveStub');
        $this->assertEquals('save_stub.model_stub_id', $relation->getQualifiedForeignKeyName());

        $model = new ModelStub;
        $relation = $model->hasOne('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelSaveStub', 'foo');
        $this->assertEquals('save_stub.foo', $relation->getQualifiedForeignKeyName());
        $this->assertSame($model, $relation->getParent());
        $this->assertInstanceOf('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelSaveStub', $relation->getQuery()->getModel());
    }

    public function testCorrectMorphClassIsReturned()
    {
        Relation::morphMap(['alias' => 'AnotherModel']);
        $model = new ModelStub;

        try {
            $this->assertEquals('Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub', $model->getMorphClass());
        } finally {
            Relation::morphMap([], false);
        }
    }

    public function testHasManyCreatesProperRelation()
    {
        $model = new ModelStub;
        $relation = $model->hasMany('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelSaveStub');
        $this->assertEquals('save_stub.model_stub_id', $relation->getQualifiedForeignKeyName());

        $model = new ModelStub;
        $relation = $model->hasMany('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelSaveStub', 'foo');
        $this->assertEquals('save_stub.foo', $relation->getQualifiedForeignKeyName());
        $this->assertSame($model, $relation->getParent());
        $this->assertInstanceOf('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelSaveStub', $relation->getQuery()->getModel());
    }

    public function testMorphManyCreatesProperRelation()
    {
        $model = new ModelStub;
        $relation = $model->morphMany('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelSaveStub', 'morph');
        $this->assertEquals('save_stub.morph_id', $relation->getQualifiedForeignKeyName());
        $this->assertEquals('morph_type', $relation->getMorphType());
        $this->assertEquals('Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub', $relation->getMorphClass());
    }

    public function testMorphToCreatesProperRelation()
    {
        $model = new ModelStub;

        // $this->morphTo();
        $relation = $model->morphToStub();
        $this->assertEquals('morph_to_stub_id', $relation->getForeignKey());
        $this->assertEquals('morph_to_stub_type', $relation->getMorphType());
        $this->assertEquals('morphToStub', $relation->getRelation());
        $this->assertSame($model, $relation->getParent());
        $this->assertInstanceOf('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelSaveStub', $relation->getQuery()->getModel());

        // $this->morphTo(null, 'type', 'id');
        $relation2 = $model->morphToStubWithKeys();
        $this->assertEquals('id', $relation2->getForeignKey());
        $this->assertEquals('type', $relation2->getMorphType());
        $this->assertEquals('morphToStubWithKeys', $relation2->getRelation());

        // $this->morphTo('someName');
        $relation3 = $model->morphToStubWithName();
        $this->assertEquals('some_name_id', $relation3->getForeignKey());
        $this->assertEquals('some_name_type', $relation3->getMorphType());
        $this->assertEquals('someName', $relation3->getRelation());

        // $this->morphTo('someName', 'type', 'id');
        $relation4 = $model->morphToStubWithNameAndKeys();
        $this->assertEquals('id', $relation4->getForeignKey());
        $this->assertEquals('type', $relation4->getMorphType());
        $this->assertEquals('someName', $relation4->getRelation());
    }

    public function testModelsAssumeTheirName()
    {
        require_once __DIR__.'/stubs/MagicModelNamespacedStub.php';

        $model = new ModelWithoutTableStub;
        $this->assertEquals('model_without_table_stub', $model->getTable());

        $namespacedModel = new \Foo\Bar\MagicModelNamespacedStub;
        $this->assertEquals('magic_model_namespaced_stub', $namespacedModel->getTable());
    }

    public function testTheMutatorCacheIsPopulated()
    {
        $class = new ModelStub;

        $expectedAttributes = [
            'list_items',
            'password',
            'appendable',
        ];

        $this->assertEquals($expectedAttributes, $class->getMutatedAttributes());
    }

    public function testRouteKeyIsPrimaryKey()
    {
        $model = new ModelNonIncrementingStub;
        $model->id = 'foo';
        $this->assertEquals('foo', $model->getRouteKey());
    }

    public function testRouteNameIsPrimaryKeyName()
    {
        $model = new ModelStub;
        $this->assertEquals('id', $model->getRouteKeyName());
    }

    public function testCloneModelMakesAFreshCopyOfTheModel()
    {
        $class = new ModelStub;
        $class->id = 1;
        $class->exists = true;
        $class->first = 'taylor';
        $class->last = 'otwell';
        $class->created_at = $class->freshTimestamp();
        $class->updated_at = $class->freshTimestamp();
        $class->setRelation('foo', ['bar']);

        $clone = $class->replicate();

        $this->assertNull($clone->id);
        $this->assertFalse($clone->exists);
        $this->assertEquals('taylor', $clone->first);
        $this->assertEquals('otwell', $clone->last);
        $this->assertObjectNotHasAttribute('created_at', $clone);
        $this->assertObjectNotHasAttribute('updated_at', $clone);
        $this->assertEquals(['bar'], $clone->foo);
    }

    public function testModelObserversCanBeAttachedToModels()
    {
        ModelStub::setEventDispatcher($events = m::mock('Globalis\PuppetSkilled\Event\Dispatcher'));
        $events->shouldReceive('listen')->once()->with('puppet.creating: Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub', 'Globalis\PuppetSkilled\Tests\Database\Magic\TestObserverStub@creating', 0);
        $events->shouldReceive('listen')->once()->with('puppet.saved: Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub', 'Globalis\PuppetSkilled\Tests\Database\Magic\TestObserverStub@saved', 0);
        $events->shouldReceive('forget');
        ModelStub::observe(new TestObserverStub);
        ModelStub::flushEventListeners();
    }

    public function testModelObserversCanBeAttachedToModelsWithString()
    {
        ModelStub::setEventDispatcher($events = m::mock('Globalis\PuppetSkilled\Event\Dispatcher'));
        $events->shouldReceive('listen')->once()->with('puppet.creating: Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub', 'Globalis\PuppetSkilled\Tests\Database\Magic\TestObserverStub@creating', 0);
        $events->shouldReceive('listen')->once()->with('puppet.saved: Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub', 'Globalis\PuppetSkilled\Tests\Database\Magic\TestObserverStub@saved', 0);
        $events->shouldReceive('forget');
        ModelStub::observe('Globalis\PuppetSkilled\Tests\Database\Magic\TestObserverStub');
        ModelStub::flushEventListeners();
    }

    public function testSetObservableEvents()
    {
        $class = new ModelStub;
        $class->setObservableEvents(['foo']);

        $this->assertContains('foo', $class->getObservableEvents());
    }

    public function testAddObservableEvent()
    {
        $class = new ModelStub;
        $class->addObservableEvents('foo');

        $this->assertContains('foo', $class->getObservableEvents());
    }

    public function testAddMultipleObserveableEvents()
    {
        $class = new ModelStub;
        $class->addObservableEvents('foo', 'bar');

        $this->assertContains('foo', $class->getObservableEvents());
        $this->assertContains('bar', $class->getObservableEvents());
    }

    public function testRemoveObservableEvent()
    {
        $class = new ModelStub;
        $class->setObservableEvents(['foo', 'bar']);
        $class->removeObservableEvents('bar');

        $this->assertNotContains('bar', $class->getObservableEvents());
    }

    public function testRemoveMultipleObservableEvents()
    {
        $class = new ModelStub;
        $class->setObservableEvents(['foo', 'bar']);
        $class->removeObservableEvents('foo', 'bar');

        $this->assertNotContains('foo', $class->getObservableEvents());
        $this->assertNotContains('bar', $class->getObservableEvents());
    }

    /**
     * @expectedException \LogicException
     */
    public function testGetModelAttributeMethodThrowsExceptionIfNotRelation()
    {
        $model = new ModelStub;
        $relation = $model->incorrectRelationStub;
    }

    public function testModelIsBootedOnUnserialize()
    {
        $model = new ModelBootingTestStub;
        $this->assertTrue(ModelBootingTestStub::isBooted());
        $model->foo = 'bar';
        $string = serialize($model);
        $model = null;
        ModelBootingTestStub::unboot();
        $this->assertFalse(ModelBootingTestStub::isBooted());
        $model = unserialize($string);
        $this->assertTrue(ModelBootingTestStub::isBooted());
    }

    public function testAppendingOfAttributes()
    {
        $model = new ModelAppendsStub;

        $this->assertTrue(isset($model->is_admin));
        $this->assertTrue(isset($model->camelCased));
        $this->assertTrue(isset($model->StudlyCased));

        $this->assertEquals('admin', $model->is_admin);
        $this->assertEquals('camelCased', $model->camelCased);
        $this->assertEquals('StudlyCased', $model->StudlyCased);

        $model->setHidden(['is_admin', 'camelCased', 'StudlyCased']);
        $this->assertEquals([], $model->toArray());

        $model->setVisible([]);
        $this->assertEquals([], $model->toArray());
    }

    public function testGetMutatedAttributes()
    {
        $model = new ModelGetMutatorsStub;

        $this->assertEquals(['first_name', 'middle_name', 'last_name'], $model->getMutatedAttributes());

        ModelGetMutatorsStub::resetMutatorCache();

        ModelGetMutatorsStub::$snakeAttributes = false;
        $this->assertEquals(['firstName', 'middleName', 'lastName'], $model->getMutatedAttributes());
    }

    public function testReplicateCreatesANewModelInstanceWithSameAttributeValues()
    {
        $model = new ModelStub;
        $model->id = 'id';
        $model->foo = 'bar';
        $model->created_at = new DateTime;
        $model->updated_at = new DateTime;
        $replicated = $model->replicate();

        $this->assertNull($replicated->id);
        $this->assertEquals('bar', $replicated->foo);
        $this->assertNull($replicated->created_at);
        $this->assertNull($replicated->updated_at);
    }

    public function testIncrementOnExistingModelCallsQueryAndSetsAttribute()
    {
        $model = m::mock('Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub[newQuery]');
        $model->exists = true;
        $model->id = 1;
        $model->syncOriginalAttribute('id');
        $model->foo = 2;

        $model->shouldReceive('newQuery')->andReturn($query = m::mock('StdClass'));
        $query->shouldReceive('where')->andReturn($query);
        $query->shouldReceive('increment');

        $model->publicIncrement('foo');

        $this->assertEquals(3, $model->foo);
        $this->assertFalse($model->isDirty());
    }

    public function testRelationshipTouchOwnersIsPropagated()
    {
        $relation = $this->getMockBuilder('Globalis\PuppetSkilled\Database\Magic\Relations\BelongsTo')->setMethods(['touch'])->disableOriginalConstructor()->getMock();
        $relation->expects($this->once())->method('touch');

        $model = m::mock('Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub[partner]');
        $model->shouldReceive('partner')->once()->andReturn($relation);
        $model->setTouchedRelations(['partner']);

        $mockPartnerModel = m::mock('Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub[touchOwners]');
        $mockPartnerModel->shouldReceive('touchOwners')->once();
        $model->setRelation('partner', $mockPartnerModel);

        $model->touchOwners();
    }

    public function testRelationshipTouchOwnersIsNotPropagatedIfNoRelationshipResult()
    {
        $relation = $this->getMockBuilder('Globalis\PuppetSkilled\Database\Magic\Relations\BelongsTo')->setMethods(['touch'])->disableOriginalConstructor()->getMock();
        $relation->expects($this->once())->method('touch');

        $model = m::mock('Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub[partner]');
        $model->shouldReceive('partner')->once()->andReturn($relation);
        $model->setTouchedRelations(['partner']);

        $model->setRelation('partner', null);

        $model->touchOwners();
    }

    public function testModelAttributesAreCastedWhenPresentInCastsArray()
    {
        $model = new ModelCastingStub;
        $model->setDateFormat('Y-m-d H:i:s');
        $model->intAttribute = '3';
        $model->floatAttribute = '4.0';
        $model->stringAttribute = 2.5;
        $model->boolAttribute = 1;
        $model->booleanAttribute = 0;
        $model->objectAttribute = ['foo' => 'bar'];
        $obj = new \StdClass;
        $obj->foo = 'bar';
        $model->arrayAttribute = $obj;
        $model->jsonAttribute = ['foo' => 'bar'];
        $model->dateAttribute = '1969-07-20';
        $model->datetimeAttribute = '1969-07-20 22:56:00';
        $model->timestampAttribute = '1969-07-20 22:56:00';

        $this->assertInternalType('int', $model->intAttribute);
        $this->assertInternalType('float', $model->floatAttribute);
        $this->assertInternalType('string', $model->stringAttribute);
        $this->assertInternalType('boolean', $model->boolAttribute);
        $this->assertInternalType('boolean', $model->booleanAttribute);
        $this->assertInternalType('object', $model->objectAttribute);
        $this->assertInternalType('array', $model->arrayAttribute);
        $this->assertInternalType('array', $model->jsonAttribute);
        $this->assertTrue($model->boolAttribute);
        $this->assertFalse($model->booleanAttribute);
        $this->assertEquals($obj, $model->objectAttribute);
        $this->assertEquals(['foo' => 'bar'], $model->arrayAttribute);
        $this->assertEquals(['foo' => 'bar'], $model->jsonAttribute);
        $this->assertEquals('{"foo":"bar"}', $model->jsonAttributeValue());
        $this->assertInstanceOf('Carbon\Carbon', $model->dateAttribute);
        $this->assertInstanceOf('Carbon\Carbon', $model->datetimeAttribute);
        $this->assertEquals('1969-07-20', $model->dateAttribute->toDateString());
        $this->assertEquals('1969-07-20 22:56:00', $model->datetimeAttribute->toDateTimeString());
        $this->assertEquals(-14173440, $model->timestampAttribute);

        $arr = $model->toArray();
        $this->assertInternalType('int', $arr['intAttribute']);
        $this->assertInternalType('float', $arr['floatAttribute']);
        $this->assertInternalType('string', $arr['stringAttribute']);
        $this->assertInternalType('boolean', $arr['boolAttribute']);
        $this->assertInternalType('boolean', $arr['booleanAttribute']);
        $this->assertInternalType('object', $arr['objectAttribute']);
        $this->assertInternalType('array', $arr['arrayAttribute']);
        $this->assertInternalType('array', $arr['jsonAttribute']);
        $this->assertTrue($arr['boolAttribute']);
        $this->assertFalse($arr['booleanAttribute']);
        $this->assertEquals($obj, $arr['objectAttribute']);
        $this->assertEquals(['foo' => 'bar'], $arr['arrayAttribute']);
        $this->assertEquals(['foo' => 'bar'], $arr['jsonAttribute']);
        $this->assertEquals('1969-07-20 00:00:00', $arr['dateAttribute']);
        $this->assertEquals('1969-07-20 22:56:00', $arr['datetimeAttribute']);
        $this->assertEquals(-14173440, $arr['timestampAttribute']);
    }

    public function testModelAttributeCastingPreservesNull()
    {
        $model = new ModelCastingStub;
        $model->intAttribute = null;
        $model->floatAttribute = null;
        $model->stringAttribute = null;
        $model->boolAttribute = null;
        $model->booleanAttribute = null;
        $model->objectAttribute = null;
        $model->arrayAttribute = null;
        $model->jsonAttribute = null;
        $model->dateAttribute = null;
        $model->datetimeAttribute = null;
        $model->timestampAttribute = null;

        $attributes = $model->getAttributes();

        $this->assertNull($attributes['intAttribute']);
        $this->assertNull($attributes['floatAttribute']);
        $this->assertNull($attributes['stringAttribute']);
        $this->assertNull($attributes['boolAttribute']);
        $this->assertNull($attributes['booleanAttribute']);
        $this->assertNull($attributes['objectAttribute']);
        $this->assertNull($attributes['arrayAttribute']);
        $this->assertNull($attributes['jsonAttribute']);
        $this->assertNull($attributes['dateAttribute']);
        $this->assertNull($attributes['datetimeAttribute']);
        $this->assertNull($attributes['timestampAttribute']);

        $this->assertNull($model->intAttribute);
        $this->assertNull($model->floatAttribute);
        $this->assertNull($model->stringAttribute);
        $this->assertNull($model->boolAttribute);
        $this->assertNull($model->booleanAttribute);
        $this->assertNull($model->objectAttribute);
        $this->assertNull($model->arrayAttribute);
        $this->assertNull($model->jsonAttribute);
        $this->assertNull($model->dateAttribute);
        $this->assertNull($model->datetimeAttribute);
        $this->assertNull($model->timestampAttribute);

        $array = $model->toArray();

        $this->assertNull($array['intAttribute']);
        $this->assertNull($array['floatAttribute']);
        $this->assertNull($array['stringAttribute']);
        $this->assertNull($array['boolAttribute']);
        $this->assertNull($array['booleanAttribute']);
        $this->assertNull($array['objectAttribute']);
        $this->assertNull($array['arrayAttribute']);
        $this->assertNull($array['jsonAttribute']);
        $this->assertNull($array['dateAttribute']);
        $this->assertNull($array['datetimeAttribute']);
        $this->assertNull($array['timestampAttribute']);
    }

    public function testUpdatingNonExistentModelFails()
    {
        $model = new ModelStub;
        $this->assertFalse($model->update());
    }

    public function testIssetBehavesCorrectlyWithAttributesAndRelationships()
    {
        $model = new ModelStub;
        $this->assertFalse(isset($model->nonexistent));

        $model->some_attribute = 'some_value';
        $this->assertTrue(isset($model->some_attribute));

        $model->setRelation('some_relation', 'some_value');
        $this->assertTrue(isset($model->some_relation));
    }

    public function testNonExistingAttributeWithInternalMethodNameDoesntCallMethod()
    {
        $model = m::mock('Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub[delete,getRelationValue]');
        $model->name = 'Spark';
        $model->shouldNotReceive('delete');
        $model->shouldReceive('getRelationValue')->once()->with('belongsToStub')->andReturn('relation');

        // Can return a normal relation
        $this->assertEquals('relation', $model->belongsToStub);

        // Can return a normal attribute
        $this->assertEquals('Spark', $model->name);

        // Returns null for a Model.php method name
        $this->assertNull($model->delete);

        $model = m::mock('Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub[delete]');
        $model->delete = 123;
        $this->assertEquals(123, $model->delete);
    }

    public function testIntKeyTypePreserved()
    {
        $model = $this->getMockBuilder('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelStub')->setMethods(['newQueryWithoutScopes', 'updateTimestamps', 'refresh'])->getMock();
        $query = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $query->shouldReceive('insertGetId')->once()->with([], 'id')->andReturn(1);
        $model->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));

        $this->assertTrue($model->save());
        $this->assertEquals(1, $model->id);
    }

    public function testStringKeyTypePreserved()
    {
        $model = $this->getMockBuilder('Globalis\PuppetSkilled\Tests\Database\Magic\MagicKeyTypeModelStub')->setMethods(['newQueryWithoutScopes', 'updateTimestamps', 'refresh'])->getMock();
        $query = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $query->shouldReceive('insertGetId')->once()->with([], 'id')->andReturn('string id');
        $model->expects($this->once())->method('newQueryWithoutScopes')->will($this->returnValue($query));

        $this->assertTrue($model->save());
        $this->assertEquals('string id', $model->id);
    }

    public function testScopesMethod()
    {
        $model = new ModelStub;

        $scopes = [
            'published',
            'category' => 'Laravel',
            'framework' => ['Laravel', '5.3'],
        ];

        $this->assertInstanceOf(Builder::class, $model->scopes($scopes));

        $this->assertSame($scopes, $model->scopesCalled);
    }

    public function testIsWithTheSameModelInstance()
    {
        $firstInstance = new ModelStub(['id' => 1]);
        $secondInstance = new ModelStub(['id' => 1]);
        $result = $firstInstance->is($secondInstance);
        $this->assertTrue($result);
    }

    public function testIsWithAnotherModelInstance()
    {
        $firstInstance = new ModelStub(['id' => 1]);
        $secondInstance = new ModelStub(['id' => 2]);
        $result = $firstInstance->is($secondInstance);
        $this->assertFalse($result);
    }

    public function testIsWithAnotherTable()
    {
        $firstInstance = new ModelStub(['id' => 1]);
        $secondInstance = new ModelStub(['id' => 1]);
        $secondInstance->setTable('foo');
        $result = $firstInstance->is($secondInstance);
        $this->assertFalse($result);
    }

    public function testIsWithAnotherConnection()
    {
        $firstInstance = new ModelStub(['id' => 1]);
        $secondInstance = new ModelStub(['id' => 1]);
        $secondInstance->setConnection('foo');
        $result = $firstInstance->is($secondInstance);
        $this->assertFalse($result);
    }
}

class TestObserverStub
{
    public function creating()
    {
    }

    public function saved()
    {
    }
}

class AbstractModelStub extends Model
{
    public function getConnection()
    {
        $mock = m::mock('CI_DB_driver');
        $mock->getQueryGrammar = new \Globalis\PuppetSkilled\Database\Query\Grammar();
        $mock->shouldReceive('getDateFormat')->andReturn('Y-m-d H:i:s')
            ->byDefault();
        return $mock;
    }
}

class ModelStub extends AbstractModelStub
{
    public $connection;
    public $scopesCalled = [];
    protected $table = 'stub';
    protected $guarded = [];
    protected $morph_to_stub_type = '\Globalis\PuppetSkilled\Tests\Database\Magic\ModelSaveStub';

    public function getListItemsAttribute($value)
    {
        return json_decode($value, true);
    }

    public function setListItemsAttribute($value)
    {
        $this->attributes['list_items'] = json_encode($value);
    }

    public function getPasswordAttribute()
    {
        return '******';
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['password_hash'] = sha1($value);
    }

    public function publicIncrement($column, $amount = 1)
    {
        return $this->increment($column, $amount);
    }

    public function belongsToStub()
    {
        return $this->belongsTo('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelSaveStub');
    }

    public function morphToStub()
    {
        return $this->morphTo();
    }

    public function morphToStubWithKeys()
    {
        return $this->morphTo(null, 'type', 'id');
    }

    public function morphToStubWithName()
    {
        return $this->morphTo('someName');
    }

    public function morphToStubWithNameAndKeys()
    {
        return $this->morphTo('someName', 'type', 'id');
    }

    public function belongsToExplicitKeyStub()
    {
        return $this->belongsTo('\Globalis\PuppetSkilled\Tests\Database\Magic\ModelSaveStub', 'foo');
    }

    public function incorrectRelationStub()
    {
        return 'foo';
    }

    public function getDates()
    {
        return [];
    }

    public function getAppendableAttribute()
    {
        return 'appended';
    }

    public function scopePublished(Builder $builder)
    {
        $this->scopesCalled[] = 'published';
    }

    public function scopeCategory(Builder $builder, $category)
    {
        $this->scopesCalled['category'] = $category;
    }

    public function scopeFramework(Builder $builder, $framework, $version)
    {
        $this->scopesCalled['framework'] = [$framework, $version];
    }
}

class ModelCamelStub extends ModelStub
{
    public static $snakeAttributes = false;
}

class DateModelStub extends ModelStub
{
    public function getDates()
    {
        return ['created_at', 'updated_at'];
    }
}

class ModelSaveStub extends AbstractModelStub
{
    protected $table = 'save_stub';
    protected $guarded = ['id'];

    public function save(array $options = [])
    {
        $_SERVER['__eloquent.saved'] = true;
    }

    public function setIncrementing($value)
    {
        $this->incrementing = $value;
    }
}

class MagicKeyTypeModelStub extends ModelStub
{
    protected $keyType = 'string';
}

class MagicModelDestroyStub extends AbstractModelStub
{
    public function newQuery()
    {
        $mock = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $mock->shouldReceive('whereIn')->once()->with('id', [1, 2, 3])->andReturn($mock);
        $mock->shouldReceive('get')->once()->andReturn([$model = m::mock('StdClass')]);
        $model->shouldReceive('delete')->once();

        return $mock;
    }
}

class ModelHydrateRawStub extends AbstractModelStub
{
    public static function hydrate(array $items, $connection = null)
    {
        return 'hydrated';
    }

    public function getConnection()
    {
        $mock = parent::getConnection();
        $mock->shouldReceive('query')
            ->once()
            ->with('SELECT ?')
            ->andReturn(m::self());
        $mock->shouldReceive('result')
            ->andReturn([]);
        return $mock;
    }
}

class ModelWithStub extends AbstractModelStub
{
    public function newQuery()
    {
        $mock = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $mock->shouldReceive('with')->once()->with(['foo', 'bar'])->andReturn('foo');

        return $mock;
    }
}

class ModelWithoutRelationStub extends AbstractModelStub
{
    public $with = ['foo'];

    protected $guarded = [];

    public function getEagerLoads()
    {
        return $this->eagerLoads;
    }
}

class ModelWithoutTableStub extends AbstractModelStub
{
}

class ModelBootingTestStub extends AbstractModelStub
{
    public static function unboot()
    {
        unset(static::$booted[static::class]);
    }

    public static function isBooted()
    {
        return array_key_exists(static::class, static::$booted);
    }
}

class ModelAppendsStub extends AbstractModelStub
{
    protected $appends = ['is_admin', 'camelCased', 'StudlyCased'];

    public function getIsAdminAttribute()
    {
        return 'admin';
    }

    public function getCamelCasedAttribute()
    {
        return 'camelCased';
    }

    public function getStudlyCasedAttribute()
    {
        return 'StudlyCased';
    }
}

class ModelGetMutatorsStub extends AbstractModelStub
{
    public static function resetMutatorCache()
    {
        static::$mutatorCache = [];
    }

    public function getFirstNameAttribute()
    {
    }

    public function getMiddleNameAttribute()
    {
    }

    public function getLastNameAttribute()
    {
    }

    public function doNotgetFirstInvalidAttribute()
    {
    }

    public function doNotGetSecondInvalidAttribute()
    {
    }

    public function doNotgetThirdInvalidAttributeEither()
    {
    }

    public function doNotGetFourthInvalidAttributeEither()
    {
    }
}

class ModelCastingStub extends AbstractModelStub
{
    protected $casts = [
        'intAttribute' => 'int',
        'floatAttribute' => 'float',
        'stringAttribute' => 'string',
        'boolAttribute' => 'bool',
        'booleanAttribute' => 'boolean',
        'objectAttribute' => 'object',
        'arrayAttribute' => 'array',
        'jsonAttribute' => 'json',
        'dateAttribute' => 'date',
        'datetimeAttribute' => 'datetime',
        'timestampAttribute' => 'timestamp',
    ];

    public function jsonAttributeValue()
    {
        return $this->attributes['jsonAttribute'];
    }
}

class ModelDynamicHiddenStub extends Model
{
    protected $table = 'stub';
    protected $guarded = [];

    public function getHidden()
    {
        return ['age', 'id'];
    }
}

class ModelDynamicVisibleStub extends Model
{
    protected $table = 'stub';
    protected $guarded = [];

    public function getVisible()
    {
        return ['name', 'id'];
    }
}

class ModelNonIncrementingStub extends Model
{
    protected $table = 'stub';
    protected $guarded = [];
    public $incrementing = false;
}
