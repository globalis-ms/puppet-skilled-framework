<?php
namespace Globalis\PuppetSkilled\Tests\Database\Magic;

use Mockery as m;
use Carbon\Carbon;

class SoftDeletingTraitTest extends \PHPUnit\Framework\TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testDeleteSetsSoftDeletedColumn()
    {
        $model = m::mock('\Globalis\PuppetSkilled\Tests\Database\Magic\SoftDeletingTraitStub');
        $model->shouldDeferMissing();
        // $model->shouldReceive('newQuery')->andReturn($query = m::mock('StdClass'));
        $model->shouldReceive('newQueryWithoutScopes')->andReturn($query = m::mock('StdClass'));
        $query->shouldReceive('where')->once()->with('id', 1)->andReturn($query);
        $query->shouldReceive('update')->once()->with(['deleted_at' => 'date-time']);
        $model->delete();

        $this->assertInstanceOf('Carbon\Carbon', $model->deleted_at);
    }

    public function testRestore()
    {
        $model = m::mock('\Globalis\PuppetSkilled\Tests\Database\Magic\SoftDeletingTraitStub');
        $model->shouldDeferMissing();
        $model->shouldReceive('fireModelEvent')->with('restoring')->andReturn(true);
        $model->shouldReceive('save')->once();
        $model->shouldReceive('fireModelEvent')->with('restored', false)->andReturn(true);

        $model->restore();

        $this->assertNull($model->deleted_at);
    }

    public function testRestoreCancel()
    {
        $model = m::mock('\Globalis\PuppetSkilled\Tests\Database\Magic\SoftDeletingTraitStub');
        $model->shouldDeferMissing();
        $model->shouldReceive('fireModelEvent')->with('restoring')->andReturn(false);
        $model->shouldReceive('save')->never();

        $this->assertFalse($model->restore());
    }
}

class SoftDeletingTraitStub
{
    use \GLobalis\PuppetSkilled\Database\Magic\SoftDeletes;
    public $deleted_at;

    public function newQuery()
    {
        //
    }

    public function getKey()
    {
        return 1;
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function save()
    {
        //
    }

    public function delete()
    {
        return $this->performDeleteOnModel();
    }

    public function fireModelEvent()
    {
        //
    }

    public function freshTimestamp()
    {
        return Carbon::now();
    }

    public function fromDateTime()
    {
        return 'date-time';
    }
}
