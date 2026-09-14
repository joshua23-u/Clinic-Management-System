#  Clinic Management System

[![Status: Development](https://img.shields.io/badge/Status-Development-blue)](#)
[![Course: BBT 2103](https://img.shields.io/badge/Course-BBT_2103_Software_Engineering-green)](#)

> **Requirements Analysis & Work Plan**  
> **Group Members:** [Insert Names and Registration Numbers Here]

Welcome to the **Clinic Management System** repository! This document outlines the foundational requirements, task allocations, and the tools our team will use to design and develop the platform.

---

## Table of Contents
1. [Introduction](#1-introduction)
2. [Functional Requirements](#2-functional-requirements)
   - [Patient Requirements](#21-patient-requirements)
   - [Doctor Requirements](#22-doctor-requirements)
   - [Admin Requirements](#23-admin-requirements)
3. [Non-Functional Requirements](#3-non-functional-requirements)
4. [Work Plan and Task Allocation](#4-work-plan-and-task-allocation)
5. [Tools and Techniques](#5-tools-and-techniques)

---

## 1. Introduction

This document presents the functional and non-functional requirements for the proposed **Clinic Management System**, along with the group's work plan, task allocation, and the tools and techniques agreed upon for the project. 

The requirements were gathered by analysing the problem statement, considering the three user roles involved (**Admin**, **Doctor**, and **Patient**), and reflecting on the manual, walk-in appointment processes common in hospitals and clinics within our local context.

---

## 2. Functional Requirements

Functional requirements define what the system must do — the specific features, behaviours, and interactions available to each user role.

### 2.1  Patient Requirements
- **`FR1.`** Allow a patient to register for an account using their personal details.
- **`FR2.`** Allow a registered patient to log in using their credentials.
- **`FR3.`** Allow a patient to manage their profile and change their password.
- **`FR4.`** Allow a patient to search for doctors by name, specialty, or locality.
- **`FR5.`** Allow a patient to view a doctor's available appointment slots.
- **`FR6.`** Allow a patient to book an appointment by selecting a doctor, date, and time slot.
- **`FR7.`** Allow a patient to view their booking history.
- **`FR8.`** Allow a patient to cancel an existing appointment.
- **`FR9.`** Allow a patient to view treatment details added by a doctor.
- **`FR10.`** Allow a patient to submit feedback to the admin after a consultation.

### 2.2  Doctor Requirements
- **`FR11.`** Allow a doctor to log in using their credentials.
- **`FR12.`** Allow a doctor to manage their profile and change their password.
- **`FR13.`** Allow a doctor to view appointment details filtered by date.
- **`FR14.`** Allow a doctor to search for a specific patient by name or ID.
- **`FR15.`** Allow a doctor to view a patient's details and past treatment history.
- **`FR16.`** Allow a doctor to add treatment records for a patient.

### 2.3  Admin Requirements
- **`FR17.`** Allow the admin to log in using their credentials.
- **`FR18.`** Allow the admin to add, update, delete, and view doctor records.
- **`FR19.`** Allow the admin to view patient details and past treatments using patient ID or name.
- **`FR20.`** Allow the admin to view appointment details filtered by date.
- **`FR21.`** Allow the admin to view feedback submitted by patients.

---

## 3. Non-Functional Requirements

Non-functional requirements define how the system should perform — the quality attributes and constraints under which it must operate.

| Category | Requirement |
| :--- | :--- |
|  **Performance** | The system shall load pages and return search or booking results within **3 seconds** under normal network conditions. |
| **Usability** | The system shall have a simple, intuitive interface usable by patients with minimal or no technical training. |
|  **Availability** | The system shall be available **24/7** to allow patients to book appointments at any time, except during scheduled maintenance. |
|  **Security** | The system shall encrypt patient passwords and restrict access to medical records based on user role. |
|  **Reliability** | The system shall accurately prevent double-booking of the same doctor, date, and time slot. |
|  **Scalability** | The system shall be able to accommodate an increasing number of doctors, patients, and appointments without a decline in performance. |
|  **Compatibility**| The system shall be accessible via common web browsers on both **desktop** and **mobile devices**. |
|  **Maintainability**| The system's codebase shall be modular and documented to allow future updates with minimal effort. |

---

## 4. Work Plan and Task Allocation

To ensure accountability and even distribution of effort, tasks for the Requirements phase have been subdivided among the six group members as follows. 

> [!NOTE]
> Roles will rotate or be adjusted as the project progresses into design and implementation, but each member retains overall responsibility for their assigned functional area. Replace 'Member 1–6' with actual group member names once roles are confirmed within the group.

| Member | Role | Assigned Tasks (Requirements Phase) |
| :--- | :--- | :--- |
| **Member 1** |  Team Lead / Coordinator | Coordinate meetings, consolidate documents, liaise with lecturer, oversee overall progress. |
| **Member 2** |  Requirements Analyst (Patient) | Elicit and document patient-side functional requirements; draft patient use cases. |
| **Member 3** |  Requirements Analyst (Doc/Admin) | Elicit and document doctor and admin functional requirements; draft related use cases. |
| **Member 4** |  Non-Functional Lead | Identify and document non-functional requirements (performance, security, usability). |
| **Member 5** |  Documentation & Quality | Compile the requirements report, proofread, format the document, maintain version control. |
| **Member 6** |  Tools & Presentation Lead | Research and set up agreed tools/techniques; prepare slides for the requirements presentation. |

---

## 5. Tools and Techniques

The group agreed on the following tools and techniques to use throughout the project, accompanied by their main advantages and disadvantages.

### 5.1  Interviews & Brainstorming (Requirements Gathering)
We will use informal interviews (role-playing as patients, doctors, and hospital staff) combined with group brainstorming sessions.
- **Advantage:** Generates a broad list of requirements quickly by drawing on collective experience.
-  **Advantage:** Low cost and easy to organise, requiring no external scheduling.
-  **Disadvantage:** Requirements may be biased since they are based on assumptions rather than direct input.
-  **Disadvantage:** Without a structured checklist, some non-functional requirements may be overlooked.

### 5.2 📄 Google Docs / Microsoft Word (Documentation)
-  **Advantage:** Supports real-time collaborative editing.
-  **Advantage:** Version history makes it easy to track changes and revert mistakes.
-  **Disadvantage:** Formatting can become inconsistent without agreed style guidelines.
-  **Disadvantage:** Requires a stable internet connection for real-time collaboration.

### 5.3  Trello (Task/Project Management)
-  **Advantage:** Visual Kanban boards make it easy to see task status (*To Do, In Progress, Done*) at a glance.
-  **Advantage:** Free for small teams and simple to learn.
-  **Disadvantage:** Limited reporting and analytics features compared to Jira.
-  **Disadvantage:** Can become disorganised if the group does not consistently update card statuses.

### 5.4  WhatsApp / Google Meet (Communication)
- **Advantage:** WhatsApp is widely used, ensuring everyone can communicate quickly.
- **Advantage:** Google Meet allows scheduled video meetings with screen sharing.
-  **Disadvantage:** WhatsApp conversations can become disorganised and hard to search.
-  **Disadvantage:** Google Meet requires reliable internet and can suffer from connectivity issues.

### 5.5 Use Case Diagrams & Data Flow Diagrams (Modelling)
-  **Advantage:** Use case diagrams clearly show the interactions between user roles.
- **Advantage:** DFDs help visualise how data moves through the system.
-  **Disadvantage:** Diagrams can oversimplify complex business rules.
-  **Disadvantage:** Requires the group to agree on a consistent notation to avoid misinterpretation.
