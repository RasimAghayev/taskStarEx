
# Task
## Interview Task: User Management System
### Objective:

Develop a User Management System using VueJS, integrated with a Laravel backend,which allows for CRUD operations on user data. The system should include at least two pages: a listing page and a detail/edit page.

### Requirements:
#### Backend: 
Use Laravel to create a RESTful API that allows for CRUD operations on user
data (Create, Read, Update, Delete).

Requirement is write backend unit tests.

#### Frontend: 
Use VueJS, with Nuxt.js for server-side rendering.

#### State Management: 
Use VUEX to manage the state of the application.

#### API Communication: 
Use Axios to communicate with the Laravel backend.

#### UI Framework: 
Use Vuetify for designing the user interface.

#### Database: 
PostgreSQL

#### Pages:
#### User List Page: 

Display a list of all users with their basic details. Should include options to add a new user or edit an existing user.

#### User Detail/Edit Page: 
Display more detailed information about a user and allow for editing of that information.

#### Bonus:

Implement search functionality on the User List Page.

Implement pagination for the User List Page.

#### Design Illustration:

1. User List Page:

A search bar at the top (bonus feature).

A "Add New User" button.

A list/table of users with basic information like Name, Email, and an "Edit" button next to each user.

2. User Detail/Edit Page:

Fields like Name, Email, Address, Phone Number, etc. Each as an editable input. "Save" and "Cancel" buttons.