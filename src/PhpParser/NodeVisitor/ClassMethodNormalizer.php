<?php

namespace Drupal\wmscaffold\PhpParser\NodeVisitor;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

class ClassMethodNormalizer extends NodeVisitorAbstract
{
    public function leaveNode(Node $node)
    {
        $node->setAttributes([]);

        if ($node instanceof Stmt\ClassMethod) {
            $node->flags = [];

            if (!$node->name instanceof Node\Identifier) {
                $node->name = new Node\Identifier($node->name);
            }
        }
    }
}
