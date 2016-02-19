@chrome @en.wikipedia.beta.wmflabs.org @firefox @vagrant
Feature: CentralAuth log in

  Background:
    Given I am using a global account

  Scenario: Test central-domain login
    Given I am logged in to the primary wiki domain
    When I visit the central login wiki domain
    Then I should be logged in

  Scenario: Test cross-domain login
    Given I am logged in to the primary wiki domain
    When I visit the alternate wiki domain
    Then I should be logged in
