# Project preparation

For running the Behat tests you will need some additional context.

* The context `Testing/Behat` is used inside the Behat feature context to boot flow.


### Resetting the database after each scenario.

By using the `FlowEntitiesTrait` and tagging the feature with `@flowEntities`, the doctrine tables will be dropped.

Make sure to create a new database for the Behat tests as otherwise your data will be lost.

## Example configuration

`Configuration/Testing/Behat/Settings.yaml`

```yaml
Neos:
  Flow:
    persistence:
      backendOptions:
        dbname: 'neos_testing_behat'
        driver: pdo_mysql
        user: ''
        password: ''
```

### Migration from `neos/behat` < 9.0

Previously when including the `FlowContextTrait`, and tagging the feature with `@fixture`, it would clear the doctrine tables after each tests.

The new `FlowBootstrapTrait` doesn't handle this anymore, but one needs to use the `FlowEntitiesTrait` and tag the feature with `@flowEntities`

Previously we advised to install behat into a separate folder via `behat:setup`, but there is currently no need for this complexity.
Now we advise to simply install behat inside the same composer distribution.
