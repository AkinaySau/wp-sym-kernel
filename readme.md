### Plain work

 - init as **mu-plugin**
 - start dependency injection
    - write base params  
    - cache
 - on action *init*:
    - create action for register DI
    - need register:  
        - router
        - configs
        - twig
        - request
        - controllers(create custom controller)
### Bugs
 1. If set *"strict_variables"* for *twig* as true not render debug, render 404. Why? And function if not exist.
