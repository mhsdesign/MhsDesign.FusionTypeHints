### Fusion Type Check package for [Neos CMS](https://www.neos.io/)

## Dont use it, it might explode ^^
(Functionality will be changed without notice.)

### This package is just a Demo how types in the Fusion Runtime could look like.
I cant claim much credit as someone else has pulled of all the work:
https://github.com/attitude/duck-types-php/blob/main/docs/Annotation.md#supported-flow-annotations
Thanks @attitude

### Installation
Install it like a normal dev package, additional add these lines:

As the base lib attitude/duck-types-php is not on packagist and not released,
you need to add the following to your ROOT! composer.json:

```json
{
  "require": {
    # this stability flag will make the package stable in composers eyes.
    "attitude/duck-types-php": "@dev"
  },
  "repositories": {
    # insert it next to distributionPackages
    "ducktypes": {
      "type": "vcs",
      "url": "https://github.com/attitude/duck-types-php"
    }
  }
}
```

### Mostly untested...
As the base lib attitude/duck-types-php has no unit tests.

### Discussion
https://discuss.neos.io/t/rfc-fusion-strict-object-api-arguments-and-typing/5762/4

### Fusion Runtime Types right now?
Please use a similar package which is already tested and working fine: https://github.com/PackageFactory/atomic-fusion-proptypes

### Examples
More specific array return types:
```
root = Neos.Fusion:Tag {
    attributes = Neos.Fusion:DataStructure {
        a = "a"
        b = "b"
        c = "c"
    }
    attributes.@type = 'string[]'
}
```

Nullable types:
```
root = Neos.Fusion:Join {
    foo = null
    foo.@type = '?string'
    bar = "bar"
    bar.@type = '?string'
}
```

Check if variable is an instantiated object of a certain class:
```
root = Neos.Fusion:Component {
    node = ${node}
    node.@type = 'Neos\\ContentRepository\\Domain\\Projection\\Content\\TraversableNodeInterface'
    
    renderer = afx`
      <h1>{node.nodeName}</h1>
    `
    @return = 'string'
}
```

Required args, without default value:
```
prototype(Foo.Bar:Required) < prototype(Neos.Fusion:Component) {
  name.@type = 'string'

  renderer = afx`
      {props.name}
  `
}

root = Foo.Bar:Required {
  # no name is passed... and this will throw an error since null is not string
}
```
