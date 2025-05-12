# Lesocle AI Pipeline Orchestration Framework

This project implements a sophisticated AI orchestration framework within Drupal, designed to create, manage, and execute complex workflows involving AI models, data processing, and content generation.

## Overview

The system enables users to build multi-step pipelines that chain together various operations, such as calls to Large Language Models (LLMs), data manipulation actions, and integration with Drupal's content and media systems. It provides a flexible and extensible architecture based on Drupal's plugin system.

## Core Modules

The framework is composed of three primary custom modules located in `web/modules/custom`:

1.  **Pipeline (`pipeline`)**:
    *   **Description**: Provides the core framework for defining and managing pipelines and their individual steps. It handles configuration, scheduling, plugin management, and the overall orchestration logic.
    *   **Key Features**: Defines `Pipeline` configuration entities, `StepTypePlugin` for individual workflow steps, scheduling capabilities, and API endpoints for external interaction.

2.  **Pipeline Run (`pipeline_run`)**:
    *   **Description**: Tracks and stores execution history, including step outcomes, performance metrics, context data, and detailed error logs. This module is crucial for monitoring, auditing, and debugging pipeline executions.
    *   **Key Features**: Defines the `PipelineRun` content entity, provides analytics and monitoring tools, manages log files, and tracks pipeline health (e.g., failure rates).

3.  **Pipeline Integration (`pipeline_integration`)**:
    *   **Description**: Connects pipeline outputs with Drupal entities, media systems (including FFmpeg integration), and external services. It enables automated content creation, media generation, and communication across different platforms.
    *   **Key Features**: Implements specialized action plugins, entity creation strategies, media handling services, and connectors for external APIs (e.g., social media).

## System Architecture

The system features a robust architecture centered around Pipelines and Steps:

*   **Pipelines**: Configurable sequences of steps, stored as Drupal configuration entities (`Pipeline`). They manage the workflow, context sharing between steps, and scheduling.
*   **Steps**: Individual units of work within a pipeline, implemented as `StepTypePlugin` plugins. Steps are ordered and can be configured independently. Examples include LLM interaction steps, custom action steps, and media processing steps.
*   **Plugin System**: Extensively uses Drupal's plugin system for `StepTypePlugin`, `LLMService` (connecting to different AI providers like OpenAI, Anthropic, Gemini), `ActionService`, and `Model` plugins, allowing easy extension.
*   **Error Handling**: A dedicated `PipelineErrorHandler` service captures detailed errors, associates them with specific steps, and logs them for analysis.

## Execution Model

The framework supports two primary execution models:

1.  **Drupal-side Execution**: Triggered manually via the UI ("Execute Now"). Uses Drupal's Batch API for processing within the Drupal environment. Suitable for testing and immediate execution needs.
2.  **Go Service Execution**: An external Go microservice polls Drupal API endpoints (`/api/pipelines/scheduled`) for scheduled pipelines. This model handles automated, potentially high-volume executions asynchronously and posts results back via API (`/pipeline/{id}/execution`).

## Installation

1.  Place the custom modules (`pipeline`, `pipeline_run`, `pipeline_integration`) in your Drupal installation's `web/modules/custom` directory.
2.  Enable the modules using the Drupal administration UI (`/admin/modules`) or Drush (`drush en pipeline pipeline_run pipeline_integration`).
3.  Configure permissions as needed at `/admin/people/permissions`.
4.  Start configuring pipelines at `/admin/config/pipeline/pipelines`.

## Technical Stack

*   **Drupal**: Version 10 or 11
*   **PHP**: Version 8.3 or higher recommended
*   **Database**: Compatible with Drupal's database requirements (MySQL/MariaDB, PostgreSQL, SQLite)
*   **External Go Service**: Required for scheduled pipeline execution (details assumed external to this repository).
*   **Dependencies**: See individual module `.info.yml` files. Core dependencies include Drupal Core APIs (Plugin, Entity API, Config Entity, Batch API, etc.).

This README provides a high-level overview. For detailed architecture, plugin implementation, and execution lifecycle information, refer to the internal documentation or code comments within the respective modules.
