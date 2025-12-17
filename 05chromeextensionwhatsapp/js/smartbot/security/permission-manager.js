/**
 * @fileoverview SmartBot Permission Manager - Sistema de permissões
 * @module smartbot/security/permission-manager
 */

/**
 * Sistema de permissões com roles e wildcards
 */
export class PermissionManager {
  constructor() {
    this.roles = new Map();
    this.userRoles = new Map();
    this.permissions = new Map();
    this.superAdmins = new Set();
    
    this._setupDefaultRoles();
  }

  /**
   * Configurar roles padrão
   * @private
   */
  _setupDefaultRoles() {
    this.addRole('admin', {
      permissions: ['*'],
      description: 'Administrador com acesso total'
    });

    this.addRole('moderator', {
      permissions: ['message:send', 'message:read', 'user:view', 'campaign:*'],
      description: 'Moderador com acesso às campanhas'
    });

    this.addRole('user', {
      permissions: ['message:send', 'message:read'],
      description: 'Usuário com acesso básico'
    });
  }

  /**
   * Adicionar role
   */
  addRole(name, config) {
    this.roles.set(name, {
      name,
      permissions: config.permissions || [],
      description: config.description || '',
      inherits: config.inherits || []
    });
  }

  /**
   * Adicionar permissão
   */
  addPermission(permission, description = '') {
    this.permissions.set(permission, { permission, description });
  }

  /**
   * Atribuir role a usuário
   */
  assignRole(userId, roleName) {
    if (!this.roles.has(roleName)) {
      throw new Error(`Role ${roleName} not found`);
    }

    if (!this.userRoles.has(userId)) {
      this.userRoles.set(userId, new Set());
    }

    this.userRoles.get(userId).add(roleName);
  }

  /**
   * Remover role de usuário
   */
  removeRole(userId, roleName) {
    if (!this.userRoles.has(userId)) return false;
    return this.userRoles.get(userId).delete(roleName);
  }

  /**
   * Verificar se usuário tem permissão
   */
  hasPermission(userId, permission) {
    // Super admin tem todas as permissões
    if (this.superAdmins.has(userId)) return true;

    const userRoles = this.userRoles.get(userId);
    if (!userRoles || userRoles.size === 0) return false;

    for (const roleName of userRoles) {
      const role = this.roles.get(roleName);
      if (!role) continue;

      // Verificar permissões da role
      if (this._roleHasPermission(role, permission)) {
        return true;
      }

      // Verificar roles herdadas
      for (const inheritedRoleName of role.inherits) {
        const inheritedRole = this.roles.get(inheritedRoleName);
        if (inheritedRole && this._roleHasPermission(inheritedRole, permission)) {
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Verificar se role tem permissão
   * @private
   */
  _roleHasPermission(role, permission) {
    for (const rolePermission of role.permissions) {
      // Permissão exata
      if (rolePermission === permission) return true;

      // Wildcard completo
      if (rolePermission === '*') return true;

      // Wildcard de namespace (ex: 'message:*')
      if (rolePermission.endsWith(':*')) {
        const namespace = rolePermission.slice(0, -2);
        if (permission.startsWith(namespace + ':')) return true;
      }
    }

    return false;
  }

  /**
   * Adicionar super admin
   */
  addSuperAdmin(userId) {
    this.superAdmins.add(userId);
  }

  /**
   * Remover super admin
   */
  removeSuperAdmin(userId) {
    return this.superAdmins.delete(userId);
  }

  /**
   * Verificar se é super admin
   */
  isSuperAdmin(userId) {
    return this.superAdmins.has(userId);
  }

  /**
   * Obter roles do usuário
   */
  getUserRoles(userId) {
    return Array.from(this.userRoles.get(userId) || []);
  }

  /**
   * Obter todas as permissões do usuário
   */
  getUserPermissions(userId) {
    if (this.superAdmins.has(userId)) return ['*'];

    const permissions = new Set();
    const userRoles = this.userRoles.get(userId) || new Set();

    for (const roleName of userRoles) {
      const role = this.roles.get(roleName);
      if (role) {
        role.permissions.forEach(p => permissions.add(p));
      }
    }

    return Array.from(permissions);
  }

  /**
   * Listar todas as roles
   */
  listRoles() {
    return Array.from(this.roles.values());
  }

  /**
   * Listar todas as permissões
   */
  listPermissions() {
    return Array.from(this.permissions.values());
  }
}
