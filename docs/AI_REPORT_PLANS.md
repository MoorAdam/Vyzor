## Goal
I want to create a page, where an AI agent can create reports based on specific parameters and presets.
It takes the data that Clarity provided, and creates a report

## Report
A report is a simple text field that an AI agent, or a user can create. In case of the AI reports, they are either requested, or automatically created. The user can edit these reports if needed

For now, the main purpose of these reports is for the user to have a simple, text based note from an AI agent, that they can use for analising the website

## Implementation

### AI report request interface

The user will have a page where they can request reports. Every project will have its own, and these reports are all porject level. 
The user can choose between presets, that will determine how and what the AI will look for and return as a report. These presets will be stored as text files, and will be injected to the AI request, when sending the request. There is a custom field as well, where the user can add their own topics. There is also a date input for selecting a range to analise. 

### Reports

When the report is created, it gets put into a list. On this list page, the user can filter for:
- Presets / types
- Date ranges

Here is the structure of a report
- Title
- Content
- Is AI
- Aspect (what the ai was looking for)
- Aspect date (range or single day)
- AI model name

The user will be able to create their own report 