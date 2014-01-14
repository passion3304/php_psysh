<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\CodeCleaner;

use PHPParser_Node as Node;
use PHPParser_Node_Stmt_Class as ClassStmt;
use PHPParser_Node_Stmt_ClassMethod as ClassMethod;
use Psy\Exception\FatalErrorException;

/**
 * The abstract class pass handles abstract classes and methods, complaining if there are too few or too many of either.
 */
class AbstractClassPass extends CodeCleanerPass
{

    private $class;
    private $abstractMethods;

    /**
     * @throws RuntimeException if the node is an abstract function with a body.
     *
     * @param Node $node
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof ClassStmt) {
            $this->class = $node;
            $this->abstractMethods = array();
        } elseif ($node instanceof ClassMethod) {
            if ($node->isAbstract()) {
                $name = sprintf('%s::%s', $this->class->name, $node->name);
                $this->abstractMethods[] = $name;

                if ($node->stmts !== null) {
                    throw new FatalErrorException(sprintf('Abstract function %s cannot contain body', $name));
                }
            }
        }
    }

    /**
     * @throws RuntimeException if the node is a non-abstract class with abstract methods.
     *
     * @param Node $node
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof ClassStmt) {
            $count = count($this->abstractMethods);
            if ($count > 0 && !$node->isAbstract()) {
                throw new FatalErrorException(sprintf(
                    'Class %s contains %d abstract method%s must therefore be declared abstract or implement the remaining methods (%s)',
                    $node->name,
                    $count,
                    ($count === 0) ? '' : 's',
                    implode(', ', $this->abstractMethods)
                ));
            }
        }
    }

}
