<?php

declare(strict_types=1);

use Inilim\Test\TestCase;
use Inilim\IPDO\DTO\QueryParamDTO;
use Inilim\IPDO\Exception\IPDOException;

final class QueryParamDTOTest extends TestCase
{
    public function tjhfh(): void
    {
        $valuesBefore = [
            'class'         => 1,
            'method'        => 2,
            'params'        => 3,
            'execute_after' => 4,
            'key_not_found' => 'dawkkjawhdlajkhwdjhwdjkj',
            'created_at' => [11, 22, 33, 44, 55],
        ];

        $valuesBefore = [
            'class'         => 1,
            'method'        => 2,
            'params'        => 3,
            'execute_after' => 4,
            'created_at'    => 11,
        ];

        $query = 'INSERT INTO tasks (class, method, params, execute_after, created_at)
                VALUES ({class}, {method}, {params}, {execute_after}, {execute_after}, ({created_at}))';

        $dto = new QueryParamDTO($query, $valuesBefore);


        $this->assertSame($dto->values, $valuesBefore);
    }

    public function testBadValuesMultiValue(): void
    {
        $this->expectException(IPDOException::class);

        $query = 'INSERT INTO tasks (class, method, params, execute_after, created_at)
                VALUES ({class}, {method}, {params}, {execute_after}, {execute_after}, ({created_at}))';

        new QueryParamDTO($query, [
            'class'         => 1,
            'method'        => 2,
            'params'        => 3,
            'execute_after' => 4,
            'key_not_found' => 'dawkkjawhdlajkhwdjhwdjkj',
            'created_at' => [11, [22], 33, 44, 55],
        ]);
    }

    public function testBadValuesNotFoundValue(): void
    {
        $this->expectException(IPDOException::class);

        $query = 'INSERT INTO tasks (class, method, params, execute_after, created_at)
                VALUES ({class}, {method}, {params}, {execute_after}, {execute_after}, ({created_at}))';

        new QueryParamDTO($query, [
            'method' => 2,
            'params' => 3,
            'execute_after' => 4,
            'key_not_found' => 'dawkkjawhdlajkhwdjhwdjkj',
            'created_at' => [11, 22, 33, 44, 55],
        ]);
    }
}
