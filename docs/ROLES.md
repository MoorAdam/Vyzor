# Roles

In the project, there are a few roles that can access specific parts of the app depending on the role's clearance.

- **Admin**: 
Can see, edit, add and manage everything on the page. The only user who can create other users

- **Web**:
Can create and manager their projects, create customer profiles, and request AI reports

- **customer**: 
This is a placeholder role for now. It has no acces anywhere. The customer

- **Collaborator**: 
The owner of a project can give this role to a user, who only has this to their assigned project.
A collaborator has the same permissions as the owner, except editing the project's properties (This can be changed, but only the owner can change the owner and collaborators)

### Permissions

A role has multiple permissions defined in a table
The system checks if the user has the right permissions based on the role (except collaborators, it checks the project if there are collaborators, and if the current user is part of it. Same with project owner)
In the core of the permission managger, if the user has admin role, it automatically unlocks everything without deffinition

When a user has no permission to parts of a project, the buttons are disabled

The permissions for the roles can be added and removed in the role_permission table in the databse

Here are the permissions:

- basics:
    - view projects
- users:
    - view users list
    - create user
    - edit users
    - remove users
    - create customer
    - edit customer
    - remove customer
- project:
    - view all projects
    - view owned projets
    - view collab projects
    - change project status
    - edit project details
    - delete project
    - create project
    - clarity:
        - view clarity snapshots
        - view clarity trends
        - fetch clarity data
    - Report:
        - view reports
        - request/create report
        - edit report
        - delete report
    - Heatmap:
        - upload heatmap
        - view heatmaps
        - edit heatmaps
        - delete heatmaps
- Context:
    - view contexts page
    - edit contexts
    - add contexts