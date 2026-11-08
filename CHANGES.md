# Changes

## 1.1.0 (2026-11-08)

Redesigned pages, same features.

- The overview shows the four stages in order as one strip, each as wide as its share of students; each stage still filters the table. The stage that waits on the lead is marked.
- One line says what happens next, with the release button, in place of the coloured box.
- Students whose marks need the lead come first in the table.

## 1.0.0 (2026-11-08)

First stable release.

- Release checks that each grade actually reached the assignment. Grades that are locked or overridden in the gradebook, or locked by the marking workflow, are reported and left unreleased instead of being shown as released.
- A marker who has already marked a student can no longer be replaced, so nobody sees someone else's mark as their own.
- Changing a mark after the lead decided sends the student back to the lead.
- The feedback field is hidden when the assignment's feedback comments are off, since that feedback could not reach students.
- Assignments that use Moodle 5.3's own multiple marking cannot also use double marking.
- Deleting a course removes its double marking data.
- Releasing needs the assignment's grade capability, checked before the first grade is written.
- Privacy: allocations, lead decisions and settings changes are exported; every stored field is declared.
- CI covers Moodle 4.5, 5.0, 5.1, 5.2 and 5.3 on PostgreSQL and MariaDB.

## 0.1.0 (2026-11-05)

First alpha.
