<?php

declare(strict_types=1);

namespace App\GraphQL;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

/**
 * GraphQL Schema definition for Helpo API.
 */
class SchemaBuilder
{
    private array $services;
    private array $types = [];

    public function __construct(array $services)
    {
        $this->services = $services;
    }

    /**
     * Build the GraphQL schema.
     *
     * @return Schema
     */
    public function build(): Schema
    {
        return new Schema([
            'query' => $this->buildQueryType(),
            'mutation' => $this->buildMutationType(),
        ]);
    }

    /**
     * Build Query type.
     *
     * @return ObjectType
     */
    private function buildQueryType(): ObjectType
    {
        return new ObjectType([
            'name' => 'Query',
            'fields' => [
                'myTickets' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($this->getTicketType()))),
                    'description' => 'Get all tickets for the authenticated user',
                    'resolve' => function ($root, $args, $context) {
                        return $this->services['ticket']->getUserTickets($context['userId']);
                    },
                ],
                'ticket' => [
                    'type' => $this->getTicketType(),
                    'description' => 'Get a single ticket by ID',
                    'args' => [
                        'id' => ['type' => Type::nonNull(Type::id())],
                    ],
                    'resolve' => function ($root, $args, $context) {
                        return $this->services['ticket']->getTicketById((int) $args['id']);
                    },
                ],
                'ticketStatus' => [
                    'type' => Type::nonNull($this->getTicketStatusType()),
                    'description' => 'Get the current status of a ticket',
                    'args' => [
                        'ticketId' => ['type' => Type::nonNull(Type::id())],
                    ],
                    'resolve' => function ($root, $args, $context) {
                        return $this->services['ticket']->getTicketStatus((int) $args['ticketId']);
                    },
                ],
                'ticketMessages' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($this->getMessageType()))),
                    'description' => 'Get all messages for a ticket',
                    'args' => [
                        'ticketId' => ['type' => Type::nonNull(Type::id())],
                    ],
                    'resolve' => function ($root, $args, $context) {
                        return $this->services['message']->getTicketMessages(
                            (int) $args['ticketId'],
                            (int) $context['userId']
                        );
                    },
                ],
            ],
        ]);
    }

    /**
     * Build Mutation type.
     *
     * @return ObjectType
     */
    private function buildMutationType(): ObjectType
    {
        return new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'login' => [
                    'type' => Type::nonNull($this->getAuthPayloadType()),
                    'description' => 'Authenticate user (auto-register if not exists)',
                    'args' => [
                        'input' => ['type' => Type::nonNull($this->getLoginInputType())],
                    ],
                    'resolve' => function ($root, $args) {
                        return $this->services['auth']->login(
                            $args['input']['login'],
                            $args['input']['password']
                        );
                    },
                ],
                'createTicket' => [
                    'type' => Type::nonNull($this->getTicketType()),
                    'description' => 'Create a new support ticket',
                    'args' => [
                        'input' => ['type' => Type::nonNull($this->getCreateTicketInputType())],
                    ],
                    'resolve' => function ($root, $args, $context) {
                        return $this->services['ticket']->createTicket(
                            $context['userId'],
                            $args['input']['subject'],
                            $args['input']['section'],
                            $args['input']['comment'] ?? null
                        );
                    },
                ],
                'sendMessage' => [
                    'type' => Type::nonNull($this->getMessageType()),
                    'description' => 'Send a message to a ticket chat',
                    'args' => [
                        'input' => ['type' => Type::nonNull($this->getCreateMessageInputType())],
                    ],
                    'resolve' => function ($root, $args, $context) {
                        return $this->services['message']->sendMessage(
                            (int) $args['input']['ticketId'],
                            $context['userId'],
                            $args['input']['content']
                        );
                    },
                ],
            ],
        ]);
    }

    /**
     * Get or create User type.
     *
     * @return ObjectType
     */
    private function getUserType(): ObjectType
    {
        if (!isset($this->types['User'])) {
            $this->types['User'] = new ObjectType([
                'name' => 'User',
                'fields' => [
                    'id' => ['type' => Type::nonNull(Type::id())],
                    'login' => ['type' => Type::nonNull(Type::string())],
                    'createdAt' => ['type' => Type::nonNull(Type::string())],
                ],
            ]);
        }
        return $this->types['User'];
    }

    /**
     * Get or create AuthPayload type.
     *
     * @return ObjectType
     */
    private function getAuthPayloadType(): ObjectType
    {
        if (!isset($this->types['AuthPayload'])) {
            $this->types['AuthPayload'] = new ObjectType([
                'name' => 'AuthPayload',
                'fields' => [
                    'token' => ['type' => Type::nonNull(Type::string())],
                    'user' => ['type' => Type::nonNull($this->getUserType())],
                ],
            ]);
        }
        return $this->types['AuthPayload'];
    }

    /**
     * Get or create LoginInput type.
     *
     * @return \GraphQL\Type\Definition\InputObjectType
     */
    private function getLoginInputType(): \GraphQL\Type\Definition\InputObjectType
    {
        if (!isset($this->types['LoginInput'])) {
            $this->types['LoginInput'] = new \GraphQL\Type\Definition\InputObjectType([
                'name' => 'LoginInput',
                'fields' => [
                    'login' => ['type' => Type::nonNull(Type::string())],
                    'password' => ['type' => Type::nonNull(Type::string())],
                ],
            ]);
        }
        return $this->types['LoginInput'];
    }

    /**
     * Get or create Ticket type.
     *
     * @return ObjectType
     */
    private function getTicketType(): ObjectType
    {
        if (!isset($this->types['Ticket'])) {
            $this->types['Ticket'] = new ObjectType([
                'name' => 'Ticket',
                'fields' => [
                    'id' => ['type' => Type::nonNull(Type::id())],
                    'userId' => ['type' => Type::nonNull(Type::id())],
                    'subject' => ['type' => Type::nonNull(Type::string())],
                    'section' => ['type' => Type::nonNull($this->getTicketSectionEnum())],
                    'comment' => ['type' => Type::string()],
                    'statusCode' => ['type' => Type::nonNull(Type::string())],
                    'statusName' => ['type' => Type::nonNull(Type::string())],
                    'createdAt' => ['type' => Type::nonNull(Type::string())],
                    'updatedAt' => ['type' => Type::nonNull(Type::string())],
                ],
            ]);
        }
        return $this->types['Ticket'];
    }

    /**
     * Get or create TicketSection enum.
     *
     * @return \GraphQL\Type\Definition\EnumType
     */
    private function getTicketSectionEnum(): \GraphQL\Type\Definition\EnumType
    {
        if (!isset($this->types['TicketSection'])) {
            $this->types['TicketSection'] = new \GraphQL\Type\Definition\EnumType([
                'name' => 'TicketSection',
                'values' => [
                    'GENERAL' => ['value' => 'GENERAL'],
                    'SUBSCRIPTION' => ['value' => 'SUBSCRIPTION'],
                    'ACCOUNT' => ['value' => 'ACCOUNT'],
                    'ERROR' => ['value' => 'ERROR'],
                    'FEATURE' => ['value' => 'FEATURE'],
                ],
            ]);
        }
        return $this->types['TicketSection'];
    }

    /**
     * Get or create TicketStatus type.
     *
     * @return ObjectType
     */
    private function getTicketStatusType(): ObjectType
    {
        if (!isset($this->types['TicketStatus'])) {
            $this->types['TicketStatus'] = new ObjectType([
                'name' => 'TicketStatus',
                'fields' => [
                    'code' => ['type' => Type::nonNull(Type::string())],
                    'name' => ['type' => Type::nonNull(Type::string())],
                    'message' => ['type' => Type::string()],
                ],
            ]);
        }
        return $this->types['TicketStatus'];
    }

    /**
     * Get or create CreateTicketInput type.
     *
     * @return \GraphQL\Type\Definition\InputObjectType
     */
    private function getCreateTicketInputType(): \GraphQL\Type\Definition\InputObjectType
    {
        if (!isset($this->types['CreateTicketInput'])) {
            $this->types['CreateTicketInput'] = new \GraphQL\Type\Definition\InputObjectType([
                'name' => 'CreateTicketInput',
                'fields' => [
                    'subject' => ['type' => Type::nonNull(Type::string())],
                    'section' => ['type' => Type::nonNull($this->getTicketSectionEnum())],
                    'comment' => ['type' => Type::string()],
                ],
            ]);
        }
        return $this->types['CreateTicketInput'];
    }

    /**
     * Get or create Message type.
     *
     * @return ObjectType
     */
    private function getMessageType(): ObjectType
    {
        if (!isset($this->types['Message'])) {
            $this->types['Message'] = new ObjectType([
                'name' => 'Message',
                'fields' => [
                    'id' => ['type' => Type::nonNull(Type::id())],
                    'ticketId' => ['type' => Type::nonNull(Type::id())],
                    'userId' => ['type' => Type::nonNull(Type::id())],
                    'senderType' => ['type' => Type::nonNull($this->getSenderTypeEnum())],
                    'content' => ['type' => Type::nonNull(Type::string())],
                    'statusCode' => ['type' => Type::string()],
                    'statusName' => ['type' => Type::string()],
                    'createdAt' => ['type' => Type::nonNull(Type::string())],
                ],
            ]);
        }
        return $this->types['Message'];
    }

    /**
     * Get or create SenderType enum.
     *
     * @return \GraphQL\Type\Definition\EnumType
     */
    private function getSenderTypeEnum(): \GraphQL\Type\Definition\EnumType
    {
        if (!isset($this->types['SenderType'])) {
            $this->types['SenderType'] = new \GraphQL\Type\Definition\EnumType([
                'name' => 'SenderType',
                'values' => [
                    'USER' => ['value' => 'USER'],
                    'SYSTEM' => ['value' => 'SYSTEM'],
                ],
            ]);
        }
        return $this->types['SenderType'];
    }

    /**
     * Get or create CreateMessageInput type.
     *
     * @return \GraphQL\Type\Definition\InputObjectType
     */
    private function getCreateMessageInputType(): \GraphQL\Type\Definition\InputObjectType
    {
        if (!isset($this->types['CreateMessageInput'])) {
            $this->types['CreateMessageInput'] = new \GraphQL\Type\Definition\InputObjectType([
                'name' => 'CreateMessageInput',
                'fields' => [
                    'ticketId' => ['type' => Type::nonNull(Type::id())],
                    'content' => ['type' => Type::nonNull(Type::string())],
                ],
            ]);
        }
        return $this->types['CreateMessageInput'];
    }
}
