# Claude Code WordPress Dating Platform Workflow

## Quick Start Commands

Once you have Agent OS and Claude Code set up, use these commands to build the WordPress dating platform:

### 1. Initial Project Setup
```
/plan-product
```
*Use the main idea and features from the setup instructions above*

### 2. First Feature Development
```
/create-spec
```
*Start with: "Core plugin architecture with user registration and basic profile system"*

### 3. Execute Development
```
/execute-tasks
```
*This will build the first feature following WordPress standards*

## Detailed Development Sequence

### Phase 1: Foundation (Weeks 1-4)

#### Week 1: Core Plugin Structure
```
/create-spec
Feature: "WordPress plugin foundation with activation, deactivation, and basic admin interface"
```

**Expected Output:**
- Main plugin file with proper header
- Activation/deactivation hooks
- Basic admin menu and settings page
- Uninstall cleanup functionality

#### Week 2: User Management Integration
```
/create-spec
Feature: "WordPress user integration with custom dating roles and capabilities"
```

**Expected Output:**
- Custom user roles (dating_member, dating_moderator)
- Capability management system
- User registration enhancements
- Profile creation on registration

#### Week 3: Database Schema
```
/create-spec
Feature: "Dating-specific database tables with proper WordPress integration"
```

**Expected Output:**
- Profile tables with foreign keys to users
- Message tables for private messaging
- Photo/media tables for profile images
- Privacy settings tables

#### Week 4: Basic Profile System
```
/create-spec
Feature: "User profile creation and editing with custom fields and photo upload"
```

**Expected Output:**
- Profile creation interface
- Custom field management
- Photo upload and management
- Profile display templates

### Phase 2: Core Features (Weeks 5-8)

#### Week 5: Search System
```
/create-spec
Feature: "Basic user search with age, location, and preference filters"
```

#### Week 6: Messaging System
```
/create-spec
Feature: "Private messaging between users with conversation threading"
```

#### Week 7: Privacy and Safety
```
/create-spec
Feature: "User blocking, reporting, and privacy controls"
```

#### Week 8: Admin Dashboard
```
/create-spec
Feature: "Administrative interface for user management and content moderation"
```

### Phase 3: Premium Extensions (Weeks 9-12)

#### Week 9: Extension Architecture
```
/create-spec
Feature: "Premium extension system with licensing and activation"
```

#### Week 10: Advanced Search Extension
```
/create-spec
Feature: "Advanced search extension with compatibility matching and saved searches"
```

#### Week 11: Real-time Chat Extension
```
/create-spec
Feature: "WebSocket-based real-time chat extension with typing indicators"
```

#### Week 12: Monetization Extension
```
/create-spec
Feature: "Membership levels and payment processing extension"
```

### Phase 4: Theme and Polish (Weeks 13-16)

#### Week 13: FSE Block Theme
```
/create-spec
Feature: "Full Site Editing block theme with dating-specific templates"
```

#### Week 14: Custom Blocks
```
/create-spec
Feature: "WordPress blocks for profile display, search forms, and match grids"
```

#### Week 15: Performance Optimization
```
/create-spec
Feature: "Caching, database optimization, and performance enhancements"
```

#### Week 16: WordPress.org Preparation
```
/create-spec
Feature: "Code review, documentation, and WordPress.org submission preparation"
```

## Claude Code Agent Configuration

### Create WordPress-Specific Sub-agents

1. **WordPress Security Validator**
   - Checks all code for security compliance
   - Validates sanitization and escaping
   - Ensures proper capability checks

2. **WordPress Standards Checker**
   - Validates coding standards compliance
   - Checks file structure and naming
   - Ensures proper documentation

3. **WordPress Accessibility Tester**
   - Tests WCAG 2.2 Level AA compliance
   - Validates form labels and ARIA
   - Checks color contrast ratios

4. **WordPress Performance Analyzer**
   - Analyzes database queries
   - Checks for N+1 query problems
   - Validates caching implementation

### Integration with Existing Agent OS Sub-agents

- **context-fetcher**: Enhanced with WordPress standards knowledge
- **file-creator**: Configured for WordPress file structures
- **git-workflow**: Set up for WordPress plugin development workflow
- **test-runner**: Configured for PHPUnit and WordPress testing

## Development Commands Reference

### Start New Feature
```
/create-spec
```
*Always specify if it's core plugin functionality or premium extension*

### Continue Development
```
/execute-tasks
```
*Continues with next task in the current spec*

### Review Progress
```
what's next?
```
*Shows next uncompleted roadmap item*

### Check Current Status
```
/analyze-product
```
*Analyzes current codebase and progress*

## WordPress-Specific Prompts

### Security Review
```
"Review all code in the current feature for WordPress security compliance. Check for:
- Input sanitization using WordPress functions
- Output escaping in all templates
- Proper nonce verification in forms
- Capability checks before sensitive operations
- Database query preparation"
```

### Standards Compliance
```
"Validate the current code against WordPress coding standards:
- PHP coding standards compliance
- Proper file and function naming
- Documentation blocks for all functions
- Internationalization for all user-facing strings
- Accessibility compliance in templates"
```

### Performance Analysis
```
"Analyze the current feature for performance issues:
- Database query optimization
- Proper use of WordPress caching
- Asset loading efficiency
- Image optimization implementation"
```

## Testing Workflow

### Manual Testing Checklist
After each feature completion:

1. **Functionality**: Does the feature work as specified?
2. **Security**: Are all inputs/outputs properly handled?
3. **Performance**: Are database queries optimized?
4. **Accessibility**: Can it be used with keyboard only?
5. **Mobile**: Does it work on mobile devices?
6. **Compatibility**: Works with latest WordPress version?

### Automated Testing
```
/test-runner
```
*Runs PHPUnit tests and WordPress compatibility checks*

## Quality Gates

Before moving to the next phase:

✅ **All features in current phase complete**
✅ **Security review passed**
✅ **Performance benchmarks met**
✅ **Accessibility requirements satisfied**
✅ **Code standards compliance verified**
✅ **Documentation updated**

## WordPress.org Submission Workflow

### Pre-submission Checklist
1. **Code Review**: Independent review of all code
2. **Security Audit**: Professional security assessment
3. **Performance Testing**: Load testing and optimization
4. **Accessibility Testing**: WCAG 2.2 compliance verification
5. **Documentation**: Complete user and developer documentation
6. **Legal Review**: License compliance and legal considerations

### Submission Process
1. **Prepare SVN Repository**: WordPress.org requires SVN
2. **Create readme.txt**: WordPress.org format requirements
3. **Submit for Review**: Initial plugin directory submission
4. **Address Feedback**: Respond to WordPress.org team feedback
5. **Approval**: Plugin approved for directory listing

This workflow ensures systematic development of a high-quality WordPress dating platform that meets all WordPress standards and provides a solid foundation for both free and premium features.
