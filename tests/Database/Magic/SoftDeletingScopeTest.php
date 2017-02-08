<?php
namespace Globalis\PuppetSkilled\Tests\Database\Magic;

use Mockery as m;

class SoftDeletingScopeTest extends \PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testApplyingScopeToABuilder()
    {
        $scope = m::mock('\Globalis\PuppetSkilled\Database\Magic\SoftDeletingScope[extend]');
        $builder = m::mock('\Globalis\PuppetSkilled\Database\Magic\Builder');
        $model = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $model->shouldReceive('getQualifiedDeletedAtColumn')->once()->andReturn('table.deleted_at');
        $builder->shouldReceive('whereNull')->once()->with('table.deleted_at');

        $scope->apply($builder, $model);
    }

    public function testForceDeleteExtension()
    {
        $builder = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $builder->shouldDeferMissing();
        $scope = new \Globalis\PuppetSkilled\Database\Magic\SoftDeletingScope;
        $scope->extend($builder);
        $callback = $builder->getMacro('forceDelete');
        $givenBuilder = m::mock('\Globalis\PuppetSkilled\Database\Magic\Builder');
        $givenBuilder->shouldReceive('getQuery')->andReturn($query = m::mock('StdClass'));
        $query->shouldReceive('delete')->once();

        $callback($givenBuilder);
    }

    public function testRestoreExtension()
    {
        $builder = m::mock('\Globalis\PuppetSkilled\Database\Magic\Builder');
        $builder->shouldDeferMissing();
        $scope = new \Globalis\PuppetSkilled\Database\Magic\SoftDeletingScope;
        $scope->extend($builder);
        $callback = $builder->getMacro('restore');
        $givenBuilder = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $givenBuilder->shouldReceive('withTrashed')->once();
        $givenBuilder->shouldReceive('getModel')->once()->andReturn($model = m::mock('StdClass'));
        $model->shouldReceive('getDeletedAtColumn')->once()->andReturn('deleted_at');
        $givenBuilder->shouldReceive('update')->once()->with(['deleted_at' => null]);

        $callback($givenBuilder);
    }

    public function testWithTrashedExtension()
    {
        $builder = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $builder->shouldDeferMissing();
        $scope = m::mock('Globalis\PuppetSkilled\Database\Magic\SoftDeletingScope[remove]');
        $scope->extend($builder);
        $callback = $builder->getMacro('withTrashed');
        $givenBuilder = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $givenBuilder->shouldReceive('getModel')->andReturn($model = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model'));
        $givenBuilder->shouldReceive('withoutGlobalScope')->with($scope)->andReturn($givenBuilder);
        $result = $callback($givenBuilder);

        $this->assertEquals($givenBuilder, $result);
    }

    public function testOnlyTrashedExtension()
    {
        $builder = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $builder->shouldDeferMissing();
        $model = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $model->shouldDeferMissing();
        $scope = m::mock('Globalis\PuppetSkilled\Database\Magic\SoftDeletingScope[remove]');
        $scope->extend($builder);
        $callback = $builder->getMacro('onlyTrashed');
        $givenBuilder = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $givenBuilder->shouldReceive('getQuery')->andReturn($query = m::mock('StdClass'));
        $givenBuilder->shouldReceive('getModel')->andReturn($model);
        $givenBuilder->shouldReceive('withoutGlobalScope')->with($scope)->andReturn($givenBuilder);
        $model->shouldReceive('getQualifiedDeletedAtColumn')->andReturn('table.deleted_at');
        $givenBuilder->shouldReceive('whereNotNull')->once()->with('table.deleted_at');
        $result = $callback($givenBuilder);

        $this->assertEquals($givenBuilder, $result);
    }

    public function testWithoutTrashedExtension()
    {
        $builder = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $builder->shouldDeferMissing();
        $model = m::mock('\Globalis\PuppetSkilled\Database\Magic\Model');
        $model->shouldDeferMissing();
        $scope = m::mock('Globalis\PuppetSkilled\Database\Magic\SoftDeletingScope[remove]');
        $scope->extend($builder);
        $callback = $builder->getMacro('withoutTrashed');
        $givenBuilder = m::mock('Globalis\PuppetSkilled\Database\Magic\Builder');
        $givenBuilder->shouldReceive('getQuery')->andReturn($query = m::mock('stdClass'));
        $givenBuilder->shouldReceive('getModel')->andReturn($model);
        $givenBuilder->shouldReceive('withoutGlobalScope')->with($scope)->andReturn($givenBuilder);
        $model->shouldReceive('getQualifiedDeletedAtColumn')->andReturn('table.deleted_at');
        $givenBuilder->shouldReceive('whereNull')->once()->with('table.deleted_at');
        $result = $callback($givenBuilder);

        $this->assertEquals($givenBuilder, $result);
    }
}
